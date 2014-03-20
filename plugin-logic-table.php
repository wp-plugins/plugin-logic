<?php	
/***
 * Create the html-table for the dashboard view
 *
 * @package	    Plugin Logic
 * @author      simon_h
 * 
 * @since       1.0.0
 * @change		1.0.2
 */
 
 // Security check
 if ( ! class_exists('WP') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( ! class_exists( 'plulo_table' ) ) {
	
	class plulo_table {

		protected static $classobj = NULL;
		public $html_output = '';
		public $plugin_base = '';
		public $all_on_dash = false;
		
		
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
		  * @since 1.0.0
		  */
		public function __construct( $plugin_basename = '', $all_on_dash = false ) {
			$this->plugin_base = $plugin_basename;
			$this->all_on_dash = $all_on_dash;
			$this->html_output = $this->create_the_table(); 
		}
		
		
		/***
		 * Get colors from the dashboard style
		 * @since 1.0.0
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
		 * @since 1.0.0
		 */
		public function create_style() { 
			return '<!-- Table Style -->
					<style type="text/css"> 
					.tftable { border:1px solid #EFEFEF; border-collapse:collapse; width:100%; }
					.tftable th { padding:8px; text-align:left;}
					.tftable tr { background-color:#fff; color:#000; }
					.tftable td { border:1px solid #EFEFEF; padding:8px; }
					#hrow { background-color:#222; color:#EEE; }
					</style>' . "\n";
		}
	
	
		/***
		 * Table HTML-Output
		 * @since 1.0.0
		 */
		public function create_the_table() { 
			global $wpdb;
			$table = $wpdb->base_prefix . 'plugin_logic';
			$structur = '';
			( get_option( 'plulo_on_dash_col' ) !== false ) ? $on_dash_columm = get_option( 'plulo_on_dash_col' ) : $on_dash_columm = '';
	
			if( !function_exists('get_plugins') ) {
				require_once (ABSPATH . 'wp-admin/includes/plugin.php');
			}
			
			$plugin_infos = array();
			if ( is_admin() ) {
				$plugin_infos = get_plugins();
			}
			
			$plugins_list = get_option( 'active_plugins', array () );
			
			// Load data from db
			$db_pl_list = array();
			if ( $wpdb->get_var( "SHOW TABLE STATUS LIKE '$table'") ) {
				$db_pl_list = $wpdb->get_results( "SELECT name, on_dashboard, logic, urls, words FROM $table ORDER BY name ASC" );
			} 
			
			// Filter inactive plugins with rules and add it to the $plugins_list
			$no_dashboard_plugs = array();
			foreach($db_pl_list as $db_pl) {
				if ( ! in_array($db_pl->name, $plugins_list ) ) {
					$no_dashboard_plugs[] = $db_pl->name;
				}
			}
			if ( count($no_dashboard_plugs ) > 0 ) {
				$plugins_list = array_merge($plugins_list, $no_dashboard_plugs);
				sort($plugins_list);
			}	
			
			// Check if relevant Plugins available
			if ( count($plugins_list) == 1 ) {
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
			
			// Create the table
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
			foreach ($plugins_list as $p) {
				if ($p == $this->plugin_base ) continue;
				$on_dashboard = 1;
				$logic = 0;
				$act_rules = array();
				$act_rules_str = '';
				
				// Check if there are rules for the Plugin in the Database
				foreach($db_pl_list as $db_pl) {
					if ($p == $db_pl->name) {
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
				$always_on = '';
				($on_dashboard == 1) ?  $always_on = 'checked' : $always_on = '';
				( !is_plugin_active($p) && !$this->all_on_dash ) ? $inactive = 'style="background:#D3D1D1;"' : $inactive = '';
				($on_dash_columm == 'checked') ? $txt_in_style = 'style="width:70%;"' : $txt_in_style = 'style="width:78%;"'; 
								
				$structur.= "<tr $inactive> \n";
				$structur.= "  <td style=\"min-width:98px;\">" . $plugin_infos[$p]['Name'] . "</td>\n";
				if ($on_dash_columm == 'checked') {
					$structur.= "  <td style=\"min-width:87px;\"> \n";
					$structur.= '	 	<input type="hidden" name="plcon_checklist['. $z .']" value="0">' . " \n";
					$structur.= '		<input type="checkbox" name="plcon_checklist['. $z .']" value="1" '. $always_on .'>'. __('Always on','plugin-logic') ."\n";
					$structur.= "  </td> \n";
				}
				$structur.= "  <td style=\"min-width:95px;\"> \n";
				$structur.= '	 	<input type="radio" name="plcon_radiolist['. $z .']" value="0" '. $select_in .'>'. __('Active on:','plugin-logic') ."<br> \n";
				$structur.= '		<input type="radio" name="plcon_radiolist['. $z .']" value="1" '. $select_ex .'>'. __('Inactive on:','plugin-logic') . "\n";
				$structur.= "  </td> \n";
				$structur.= '  <td '. $txt_in_style .' ><textarea name="plcon_txt_list['. $z .']" style="width:100%; min-height:74px">'. $act_rules_str .'</textarea></td>' . "\n";
				$structur.= "</tr> \n";

				$z++;
			}
			$structur.= "</table> \n";
			return $structur;
		}
			
	} // end class
	
} // end if class exists
