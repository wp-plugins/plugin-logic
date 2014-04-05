<?php	
/***
 * Create the settings page for the dashboard view
 *
 * @package	    Plugin Logic
 * @author      simon_h
 * 
 * @since       1.0.0
 * @change		1.0.4
 */
 
// Security check
if ( ! class_exists('WP') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( ! class_exists('plulo_fields') ) {
	
	class plulo_fields {

		protected static $classobj = NULL;
		public $html_output = '';
		public $plugin_base = '';
		
		
		/***
		 * Handler for the action 'init'. Instantiates this class.
		 * @since 1.0.0
		 */
		public static function init() {
			NULL === self::$classobj and self::$classobj = new self();
			return self::$classobj;
		}
		
	
		 /***
		  * Install settings;
		  * @since 1.0.4
		  */
		public function __construct( $plugin_basename = '' ) {
			$this->plugin_base = $plugin_basename;

			if ( is_multisite() ) 
				$this->html_output = $this->create_tabs_and_table();
			else
				$this->html_output = $this->create_the_fields(); 
		}
		
		
		/***
		 * Get colors from the dashboard style
		 * 
		 * @since 1.0.0
		 * @change 1.0.3
		 */
		public function get_adminbar_colors(){
			?>
			<script type="text/javascript">

				function getStyle(el, cssprop){
					if (el.currentStyle) //IE
						return el.currentStyle[cssprop]
					else if (document.defaultView && document.defaultView.getComputedStyle) //Firefox
						return document.defaultView.getComputedStyle(el, "")[cssprop]
					else //try and get inline style
						return el.style[cssprop]
				}

				function rgbStringToHex(rgbStr){
					var a = rgbStr.split("(")[1].split(")")[0];
					a = a.split(",");
					var b = a.map(function(x){             
						x = parseInt(x).toString(16);      //Convert to a base16 string
						return (x.length==1) ? "0"+x : x;  //Add zero if we get only one character
					})
					return "#"+b.join("");
				}	
						
				var wpadminbar = document.getElementById("wpadminbar");
				var table = document.getElementById("hrow");
				
				// Paint the table headline with admin colors 
				if ( wpadminbar ) {
					admin_background = rgbStringToHex( getStyle(wpadminbar, 'backgroundColor') );
					admin_color = rgbStringToHex( getStyle(wpadminbar, 'color') );
				
					table.style.background = admin_background;
					table.style.color = admin_color;
					
				} else {
					table.style.background = '#222';
					table.style.color = '#EEE';
				}
		
			</script> 
			<?php
		}
				

		/***
		 * Table CSS-Style
		 * 
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function create_style() { 
			return '<!-- Table Style -->
					<style type="text/css"> 
					.tftable { border:1px solid #EFEFEF; border-collapse:collapse; width:100%; font-size:12px; }
					.tftable th { padding:8px; text-align:left;}
					.tftable tr { background-color:#fff; color:#000; }
					.tftable td { border:1px solid #EFEFEF; padding:8px; }
					#hrow { background-color:#222; color:#EEE; }
					</style>' . "\n";
		}
	
	
		/***
		 * Table HTML-Output
		 * 
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function create_the_fields( $blog_id = 0 ) { 
			global $wpdb;
			$structur = '';
			( get_option( 'plulo_on_dash_col' ) !== false ) ? $on_dash_columm = get_option( 'plulo_on_dash_col' ) : $on_dash_columm = '';
	
			if( !function_exists('get_plugins') ) {
				require_once (ABSPATH . 'wp-admin/includes/plugin.php');
			}
			
			$plugin_infos = array();
			if ( is_admin() ) {
				$plugin_infos = get_plugins();
			}
			
			// Get activated plugins
			$active_plugin_list = array();
			if ( is_multisite() ) {
				
				if ($blog_id == 0) {
					$sitewide_plugins_opts = get_site_option( 'active_sitewide_plugins', array () );
					foreach($sitewide_plugins_opts as $key => $value) 
						$active_plugin_list[] = $key;
				} elseif ( $blog_id == 1 ) {
					$active_plugin_list = get_blog_option( $blog_id, 'active_plugins', array () );
				} else {
					//$curr_blog = ( $blog_id > 1 ) ? $blog_id.'_' : '';
					$active_plugin_list = unserialize( $wpdb->get_var( 
															"SELECT option_value FROM " . 
															"{$wpdb->base_prefix}{$blog_id}_options" . 
															" WHERE option_name = 'active_plugins'" ) );
				}
					
			} else 
				$active_plugin_list = get_option( 'active_plugins', array () ); 

			// Load data from db
			$db_pl_list = array();
			if ( $wpdb->get_var( "SHOW TABLE STATUS LIKE '". PLULO_DBTABLE ."'") ) 
				$db_pl_list = $wpdb->get_results( "SELECT * FROM ". PLULO_DBTABLE ." ORDER BY name ASC" ); 
			
			// Filter inactive plugins with rules and add it to the $active_plugin_list
			$inactive_rule_plugs = array();
			$plugins_for_output = $active_plugin_list;
			foreach($db_pl_list as $db_pl) {
				if ( is_multisite() && ($blog_id != $db_pl->blog_id) )
					continue;
				elseif ( ! in_array($db_pl->name, $active_plugin_list ) )
					$inactive_rule_plugs[] = $db_pl->name;
			}
			if ( count($inactive_rule_plugs ) > 0 ) {
				$plugins_for_output = array_merge($active_plugin_list, $inactive_rule_plugs);
				sort($plugins_for_output);
			}
			
			// Check if relevant Plugins available 
			$min_count = ( is_multisite() && ($blog_id != 0) ) ? 0 : 1;
			if ( count($plugins_for_output) == $min_count ) { 
				add_action('admin_footer', function () {
					?>
					<script type="text/javascript">
						var tableFoot = document.getElementById("tfoot");
						tableFoot.style.display = 'none';
					</script> 
					<?php
					}
				);
				$structur .= "<div class=\"update-nag\">\n"; 
				$structur .= "	<h4>". __('There are no active Plugins or inactive Plugins with Rules.','plugin-logic') ."</h4>\n"; 
				$structur .= "</div>\n"; 
				return $structur;
			}
			
			// Create the html-table
			if ( get_user_option('admin_color') != 'fresh' ) {
				add_action('admin_footer',array($this, 'get_adminbar_colors') ) ;
			}	
			$structur.= $this->create_style(); 
			$structur.= '<table class="tftable" border="1">'."\n"; 
			$structur.= "<tr id=\"hrow\">\n";
			$structur.= "	<th>". __('Activated Plugins','plugin-logic') ."</th>\n";
			if ($on_dash_columm == 'checked') $structur.= "	<th>". __('Behavior on Dashbord','plugin-logic') ."</th>\n";
			$structur.= "	<th>". __('Active / Inactive', 'plugin-logic') ."</th>\n";
			$structur.= "	<th>". __('Urls or occurring Words', 'plugin-logic') ."</th>\n";
			$structur.= "</tr>\n";
			
			$z = 0;		
			foreach ($plugins_for_output as $p) {
				if ($p == $this->plugin_base ) continue;
				$on_dashboard = 1;
				$logic = 0;
				$act_rules = array();
				$act_rules_str = '';
				
				// Check if there are rules for the Plugin in the Database
				foreach($db_pl_list as $db_pl) {
					if ( is_multisite() ) {
						if ( ($p == $db_pl->name) && ($blog_id == $db_pl->blog_id) ) {
							$act_rules = array_merge( unserialize($db_pl->urls), unserialize($db_pl->words) );
							$act_rules_str = implode(",\n", $act_rules);
							$logic = $db_pl->logic;
							$on_dashboard = $db_pl->on_dashboard;
							break;
						}
					} elseif ($p == $db_pl->name) {
						$act_rules = array_merge( unserialize($db_pl->urls), unserialize($db_pl->words) );
						$act_rules_str = implode(",\n", $act_rules);
						$logic = $db_pl->logic;
						$on_dashboard = $db_pl->on_dashboard;
						break; 
					}	
				}
				
				$select_in = '';
				$select_ex = '';
				($logic == 0) ?  $select_in = 'checked' : $select_ex = 'checked';
				$always_on = ($on_dashboard == 1) ? 'checked' : '';
				
				$inactive = ( !in_array($p, $active_plugin_list) ) ? 'style="background:#D3D1D1;"' : '';		
								
				$txt_in_style = ($on_dash_columm == 'checked') ? 'style="width:70%;"' : 'style="width:78%;"'; 
								
				$structur.= "<tr $inactive> \n";
				$structur.= "  <td style=\"min-width:98px;\">" . $plugin_infos[$p]['Name'] . "</td>\n";
				if ($on_dash_columm == 'checked') {
					$structur.= "  <td style=\"min-width:87px;\"> \n";
					$structur.= '	 	<input type="hidden" name="plulo_checklist['. $z .']" value="0">' . " \n";
					$structur.= '		<input type="checkbox" name="plulo_checklist['. $z .']" value="1" '. $always_on .'>'. __('Always on','plugin-logic') ."\n";
					$structur.= "  </td> \n";
				}
				$structur.= "  <td style=\"min-width:95px;\"> \n";
				$structur.= '	 	<input type="radio" name="plulo_radiolist['. $z .']" value="0" '. $select_in .'>'. __('Active on:','plugin-logic') ."<br> \n";
				$structur.= '		<input type="radio" name="plulo_radiolist['. $z .']" value="1" '. $select_ex .'>'. __('Inactive on:','plugin-logic') . "\n";
				$structur.= "  </td> \n";
				$structur.= '  <td '. $txt_in_style .' ><textarea name="plulo_txt_list['. $z .']" style="width:100%; min-height:74px">'. $act_rules_str .'</textarea></td>' . "\n";
				$structur.= "</tr> \n";

				$z++;
			}
			$structur.= "</table> \n";
			return $structur;
		}
		
		
		/***
		 * Creates the tab navigation for the options page on multisites
		 * @since 1.0.4
		 */
		public function create_tabs_and_table() {
			
			// Get all blog options
			global $wpdb;
			$blogs = $wpdb->get_results(
				$wpdb->prepare( 
					"SELECT blog_id
					FROM {$wpdb->blogs}
					WHERE site_id = %d
					AND spam = '0'
					AND deleted = '0'
					AND archived = '0'"
					, $wpdb->siteid 
				)
			);
			
			$active_tab = isset($_GET['tabid']) ? $_GET['tabid'] : '0';
			if(isset($_GET['tabid'])) $active_tab = $_GET['tabid'];
			
			$tabs_output = '';
			$tabs_output .= "<h2 class=\"nav-tab-wrapper\"> \n";
			($active_tab == '0') ? $selectet = 'nav-tab-active' : $selectet = '';
			$tabs_output .= "<a href=\"?page=plugin-logic&amp;tabid=0\" class=\"nav-tab $selectet\">". __('Sitewide','plugin-logic') ."</a> \n";
			
			// Iterate Through All Sites
			$blog_range = get_option( 'plulo_blog_range', array(1,10) );
			
			$z = 0;
			foreach ($blogs as $blog) {
				$z++;
				if ( $z == $blog_range[1] + 1 ) break;
				if ( $z < $blog_range[0] ) continue;
				
				$blogname = get_blog_option($blog->blog_id, 'blogname');
				
				($active_tab == $blog->blog_id) ? $selectet = 'nav-tab-active' : $selectet = '';
				$tabs_output .= "<a href=\"?page=plugin-logic&amp;tabid={$blog->blog_id}\" class=\"nav-tab $selectet\"> $blogname </a> \n";
			}
			
			$tabs_output .= "</h2> \n";
			
			if ($active_tab == '0') { 
				$tabs_output .=	'<div id="poststuff" class="ui-sortable meta-box-sortables">' . "\n";
				$tabs_output .=		"<div class=\"postbox\"> \n";
				$tabs_output .=			"<div class=\"inside\"><p> {$this->create_the_fields()} </p></div> \n";
				$tabs_output .=		"</div> \n";
				$tabs_output .=	"</div> \n";
			 } 
			
			// Iterate Through All Sites
			$z = 0;
			foreach ($blogs as $blog) {
				$z++;
				if ( $z == $blog_range[1] + 1 ) break;
				if ( $z < $blog_range[0] ) continue;
				
				$blogname = get_blog_option($blog->blog_id, 'blogname');
				
				if ($active_tab == $blog->blog_id) { 
					$tabs_output .=	'<div id="poststuff" class="ui-sortable meta-box-sortables">' . "\n";
					$tabs_output .=		"<div class=\"postbox\"> \n";
					$tabs_output .=			"<div class=\"inside\"> <p> {$this->create_the_fields($blog->blog_id)} </p> </div> \n";
					$tabs_output .=		"</div> \n";
					$tabs_output .=	"</div> \n";
					
					break;
				} 
			
			}
			
			return $tabs_output;
		}
			
	} // end class
	
} // end if class exists
