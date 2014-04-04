<?php
/***
 * Plugin Name: Plugin Logic
 * Plugin URI: http://wordpress.org/plugins/plugin-logic/
 * Description: Activate plugins on pages only if they are really needed.  
 * Author: simon_h
 * Version: 1.0.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
 
// Security check
if ( ! class_exists('WP') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}
 
if ( ! class_exists('plugin_logic') ) {
	
	define( 'PLULO_DBTABLE',  $GLOBALS['wpdb']->base_prefix . 'plugin_logic' );
	
	// include plugin on hook
	add_action( 'plugins_loaded',       array( 'plugin_logic', 'init' ) );
	register_activation_hook( __FILE__, array( 'plugin_logic', 'on_activation' ) );
	
	class plugin_logic {
		
		protected static $classobj = NULL;
	
		/***
		 * Handler for the action 'init'. Instantiates this class.
		 * @since 1.0.0
		 */
		public static function init() {
			NULL === self::$classobj and self::$classobj = new self();
			return self::$classobj;
		}
		
	
		 /***
		  * Init class properties, register hooks, add menu entries; 
		  * 
		  * @since 1.0.0
		  * @change 1.0.4
		  */
		public function __construct() {
			$this->on_dash_columm = get_option( 'plulo_on_dash_col', '');
			$this->plugin_base = plugin_basename( __FILE__ );
			
			register_deactivation_hook( __FILE__, array( $this, 'on_deactivation' ) );
			register_uninstall_hook( __FILE__,    array( 'plugin_logic', 'on_uninstall' ) );
			
			load_plugin_textdomain( 'plugin-logic', false, dirname(plugin_basename(__FILE__)) . '/I18n/' );
			
			if ( is_multisite() ) {
				$this->blog_range = get_option( 'plulo_blog_range', array(1,10) );
				
				// Menu entries
				add_filter( "network_admin_plugin_action_links_{$this->plugin_base}", array( $this, 'plugin_add_settings_link' ) ); 
				add_action( 'network_admin_menu', array( $this,'on_admin_menu') );
			} else {
				add_filter( "plugin_action_links_{$this->plugin_base}", array( $this, 'plugin_add_settings_link' ) );
				add_action( 'admin_menu', array( $this,'on_admin_menu') );
			}	
		} 
		
		
		/***
		 * Add plugin settings link to plugins list table
		 * @since 1.0.0
		 */
		public function plugin_add_settings_link( $links ) {
			$settings_link = '<a href="plugins.php?page=plugin-logic">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
				return $links;
		}

		
		/***
		 * Add the menu entry to the plugins-options-page and add access to the screen-options-wrap
		 * @since 1.0.0
		 */
		public function on_admin_menu() {
			$this->pagehook = add_plugins_page( 'Plugin Logic', 'Plugin Logic', 'activate_plugins', 'plugin-logic', array( $this,'plulo_option_page' ) ); 
			add_action("load-{$this->pagehook}", array( $this,'register_screen_options_wrap' ));
		}
		
		
		/***
		 * Register a new screen-options-wrap and add custom HTML to the screen-options-wrap panel.
		 * 
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function register_screen_options_wrap(){
			$screen = get_current_screen(); 
			 
			$network_slug = ( is_multisite() ) ? '-network' : '';
			if(!is_object($screen) || $screen->id != $this->pagehook . $network_slug) return;
			 
			$screen->add_option('my_option', ''); 
			add_filter('screen_layout_columns', array( $this,'screen_options_controls') ); 
		}
		
		
		/***
		 * Check if screen-options-wrap controls changed and output the html-code for the controls.
		 * 
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function screen_options_controls() {
			
			if( is_multisite() && isset($_POST['plulo_submit_range'])  ) { 
				if (!is_user_logged_in() || !current_user_can('activate_plugins') )
					wp_die( __('Cheatin&#8217; uh?') );		
				
				// Check user input
				$start = ( is_numeric($_POST['plulo_start_blog']) ) ? $_POST['plulo_start_blog'] : 1;
				$end = ( is_numeric($_POST['plulo_end_blog']) ) ? $_POST['plulo_end_blog'] : $start+10;
				
				if ( ($start > $end) ||  ($start < 1) ||  ($end < 1) ) {
					$start = 1;
					$end = 10;
				}	
				
				$this->blog_range[0] = $start;
				$this->blog_range[1] = $end;

				// Update the Database with the new range for displayed blogs as tabs
				if ( get_option( 'plulo_blog_range' ) !== false ) 
					update_option( 'plulo_blog_range', $this->blog_range );
				else 
					add_option( 'plulo_blog_range', $this->blog_range, null, '');	

			}

			if( isset($_POST['plulo_toggle_dash_col'])  ) { 
				if (!is_user_logged_in() || !current_user_can('activate_plugins') )
					wp_die( __('Cheatin&#8217; uh?') );		
					
				$this->on_dash_columm = $_POST['plulo_toggle_dash_col'];

				// Update the Database with the new on dashboard behavior option
				if ( get_option( 'plulo_on_dash_col' ) !== false ) 
					update_option( 'plulo_on_dash_col', $this->on_dash_columm );
				else 
					add_option( 'plulo_on_dash_col', $this->on_dash_columm, null, '');		
			}
			
			?>
			<div style="padding:15px 0 0 15px;">
				<form action="" method="post">
					
					<?php if ( is_multisite() ) { ?>
					<label><?php _e( 'Show Blogs: ', 'plugin-logic' )?></label>
					<input name="plulo_start_blog" value="<?php echo $this->blog_range[0]; ?>" type="number" size="3"/> - 
					<input name="plulo_end_blog" value="<?php echo $this->blog_range[1]; ?>" type="number" size="3"/> &nbsp; 
					<input name="plulo_submit_range" type="submit" value="<?php _e( 'Save Changes' )?>" class="button-secondary"/>
					<hr>
					<?php } ?>
					
					<p>
						<input name="plulo_toggle_dash_col" type="hidden" value=""/>
						<input name="plulo_toggle_dash_col" type="checkbox" value="checked" onChange="this.form.submit()" <?php echo $this->on_dash_columm; ?> /> 
							<?php _e('Show Options for Behavoir on Dashboard', 'plugin-logic') ?>
					</p>	
				</form>
			</div>
			<?php
		}
		
		
		 /***
		  * Plugin Logic options page for the Dashboard
		  * 
		  * @since 1.0.0
		  * @change 1.0.4
		  */
		public function plulo_option_page() {
			global $wpdb;	
			$write_error = false;
			$all_on_dash = false;
			
			// Action if Save-Button pressed 
			if( isset($_POST['plulo_submit']) ) {
				if (!is_user_logged_in() || !current_user_can('activate_plugins') )
					wp_die( __('Cheatin&#8217; uh?') );		
			
				// Get active plugins
				$active_plugin_list = array();
				if ( is_multisite() ) {
					$selected_blog = isset($_GET['tabid']) ? $_GET['tabid'] : '0';
					
					if ( $selected_blog == 0 ) {
						$sitewide_plugins_opts = get_site_option( 'active_sitewide_plugins', array () );
						foreach($sitewide_plugins_opts as $key => $value) 
							$active_plugin_list[] = $key;
					} elseif ( $selected_blog == 1 ) {
						$active_plugin_list = get_blog_option( $selected_blog, 'active_plugins', array () );
					} else {
						//$curr_blog = ( $selected_blog > 1 ) ? $selected_blog.'_' : '';
						$active_plugin_list = unserialize( $wpdb->get_var( 
																"SELECT option_value FROM " . 
																"{$wpdb->base_prefix}{$selected_blog}_options" . 
																" WHERE option_name = 'active_plugins'" ) );
					}	
						
				} else		
					$active_plugin_list = get_option( 'active_plugins', array () );	
					
				// Load data from db
				if ( !$wpdb->get_var( "SHOW TABLE STATUS LIKE '". PLULO_DBTABLE ."'") ) 
					plugin_logic::create_databse_table();	
				
				$order = ( is_multisite() ) ? "blog_id" : "name";
				$old_db_list = $wpdb->get_results( "SELECT * FROM ". PLULO_DBTABLE ." ORDER BY $order ASC" );

				// Filter inactive Plugins with rules and add it to the $all_plugin_list
				$no_dashboard_plugs = array();
				$all_plugin_list = $active_plugin_list;
				foreach($old_db_list as $db_pl) {
					if ( is_multisite() ) {
						if ( ( $selected_blog == $db_pl->blog_id ) &&  (! in_array($db_pl->name, $active_plugin_list )) )
							$no_dashboard_plugs[] = $db_pl->name;
							
					} elseif ( ! in_array($db_pl->name, $active_plugin_list ) ) {
						$no_dashboard_plugs[] = $db_pl->name;
					}
				}
				
				if ( count($no_dashboard_plugs ) > 0 ) {
					$all_plugin_list = array_merge($active_plugin_list, $no_dashboard_plugs);
					sort($all_plugin_list);
				}	
				
				
				
				// Get user input			
				$check_array = array();
				if (isset($_POST['plulo_checklist'])) {
					// Check-Button-List with dashboard bevavior options
					$check_array = $_POST['plulo_checklist'];
				}
				
				$radio_array = array();
				if (isset($_POST['plulo_radiolist'])) {
					// Radio-Button-List with logic options
					$radio_array = $_POST['plulo_radiolist'];
				}
				
				$user_txt_input = array();
				if (isset($_POST['plulo_txt_list'])) {
					// User rules as textinput
					$user_txt_input = $_POST['plulo_txt_list'];
				}
					
				// Save new values to the database and create activation/deactivation rules 
				$z = 0;	 
				$plugin_rules = '';
				foreach ($all_plugin_list as $path) {
					if ( $path == $this->plugin_base ) continue;
					
					// Filter user input
					$buffer = str_replace(array("\r", "\n", "\t", " "), "", $user_txt_input[$z]);
					$buffer = strtolower($buffer);
					$url_rules = array();
					$word_rules = array();
					if ( $buffer !== '' ) {
						if ($buffer[strlen($buffer)-1] == ',') $buffer = substr($buffer, 0, strlen($buffer)-1); 
						$all_rules = explode(",", $buffer);
						$url_rules = array_filter( $all_rules, array($this, 'is_url') );
						$word_rules = array_filter( $all_rules, array($this, 'is_word') );
					}	

					if ( (count($url_rules) > 0) || (count($word_rules) > 0) ) { // Rules exists
					
						if ( is_multisite() ) 
							$query_to_get_var = PLULO_DBTABLE . " WHERE blog_id Like '$selected_blog' AND name Like '$path'";
						else		
							$query_to_get_var = PLULO_DBTABLE . " WHERE name Like '$path'"; 
						
						if ($this->on_dash_columm == 'checked') {						
							$on_dashboard = $check_array[$z];
						} else {
							$on_dashboard = $wpdb->get_var( "SELECT on_dashboard FROM " . $query_to_get_var );
							if ( $on_dashboard == NULL ) $on_dashboard = '1'; 
						}	
							
						$logic = $radio_array[$z];
					
						// Write updates to database  
						$db_row_exists = $wpdb->get_var( "SELECT name FROM " . $query_to_get_var );
			
						if ( $db_row_exists != NULL ) {
							
							if ( is_multisite() ) {
								$wpdb->update( PLULO_DBTABLE, array(
															'blog_id' => $selected_blog,
															'name' => $path ,
															'on_dashboard' => $on_dashboard,
															'logic' => $logic, 
															'urls' => serialize($url_rules), 
															'words' => serialize($word_rules) 
															), array('blog_id' => $selected_blog, 'name' => $path) 
												);
							} else { 
								$wpdb->update( PLULO_DBTABLE, array(
															'name' => $path ,
															'on_dashboard' => $on_dashboard,
															'logic' => $logic, 
															'urls' => serialize($url_rules), 
															'words' => serialize($word_rules) 
															), array('name' => $path) 
													);
							}						
											
						} else {
							
							if ( is_multisite() ) {
								$wpdb->insert( PLULO_DBTABLE, array(
														'blog_id' => $selected_blog,
														'name' => $path ,
														'on_dashboard' => $on_dashboard,
														'logic' => $logic, 
														'urls' => serialize($url_rules), 
														'words' => serialize($word_rules) 
														)
												);
							} else {
								$wpdb->insert( PLULO_DBTABLE, array(
														'name' => $path ,
														'on_dashboard' => $on_dashboard,
														'logic' => $logic, 
														'urls' => serialize($url_rules), 
														'words' => serialize($word_rules) 
														)
									);					
							}						
						
						}						
								
											
						// Prevent reactivation bug
						if ( is_multisite() ) {
							
							if ( ($on_dashboard == 1) && !in_array($path, $active_plugin_list) ) {
								if ( $selected_blog == 0 ) {
									require_once(ABSPATH .'/wp-admin/includes/plugin.php');
									activate_plugin($path, '', true, false);
								} else {
									$active_plugin_list[] = $path;
									sort($active_plugin_list);
									update_blog_option($selected_blog, 'active_plugins', $active_plugin_list);
								}
									
							}	
						}	
						elseif ( ($on_dashboard == 1) && !is_plugin_active($path) ) {
							require_once(ABSPATH .'/wp-admin/includes/plugin.php');
							activate_plugin($path);
						}
						
							
					} else {
						// Delete database entry if user input is empty
						if ( is_multisite() ) 
							$wpdb->delete( PLULO_DBTABLE, array('blog_id' => $selected_blog, 'name' => $path) );
						else	
							$wpdb->delete( PLULO_DBTABLE, array('name' => $path) );	
					}
					
					$z++;
				}
				
				// Create the rules from the updated database
				if ( $wpdb->get_var( "SHOW TABLE STATUS LIKE '". PLULO_DBTABLE ."'") ) {
					
					if ( is_multisite() ) {
						$multisite_rules = $wpdb->get_results( "SELECT * FROM ". PLULO_DBTABLE ." ORDER BY blog_id ASC" );
						$content = $this->rules_for_mulitsite( $multisite_rules );
						$write_error = $this->write_rule_file( $content );
					} else {
						$singelsite_rules = $wpdb->get_results( "SELECT * FROM ". PLULO_DBTABLE );
						$content = $this->rules_for_singlesite( $singelsite_rules );
						$write_error = $this->write_rule_file( $content );
					}	
					
				}
				
				// Second site refresh to get the new Plugin status
				if ( !$all_on_dash && !is_array($write_error) ) $this->reload_with_ajax(); 
			}
			
			require_once 'plugin-logic-fields.php';
			$plulo_fields = new plulo_fields( $this->plugin_base, $all_on_dash );		

			?>		
			<!-- Plugin Logic setting page -->		
			<div class="wrap">
				<h2>Plugin Logic</h2> <br>
				<form action="" method="post">
					<?php 
					if (is_array($write_error)) echo $write_error[0]; 
					echo $plulo_fields->html_output;
					?>
					<div id="tfoot" style="margin-top:10px">
						<input name="plulo_submit" type="submit" value="<?php _e( 'Save Changes' )?>" class="button-primary"/>
					</div>	
				</form>
			</div>			
			<?php
		}
		
			
		/***
		 * Filter callback function checks if array-element is an url
		 * @since 1.0.0
		 */
		public function is_url($var) {
			return( ('http://' == substr($var,0,7)) || ('https://' == substr($var,0,8)) );
		}
		
		/***
		 * Filter callback function checks if array-element is not an url
		 * @since 1.0.0
		 */
		public function is_word($var) {
			return( ! $this->is_url($var) );
		}
		
		
		/***
		 * Reload page with ajax and jQuery
		 * @since 1.0.0
		 */
		public function reload_with_ajax() {
	
			add_action('admin_footer', function () {
					?>	
					<script type="text/javascript">
						//<![CDATA[
						jQuery(document).ready( function($) {
							$.ajax({
								url: "",
								context: document.body,
								success: function(s,x){
									$(this).html(s);
								}
							});
						});
						//]]>
					</script>			
					<?php
					}
				);
		}
			
		
		/***
		 * Creates the plugin activation/deactivation rules for singlesite installations
		 * @since 1.0.4
		 */
		public static function rules_for_singlesite( $singlesite_rules = array() ) {
			$rule_file_content = '';
			
			if ( !empty($singlesite_rules) ) {
			
				$all_on_dash = true;
				foreach( $singlesite_rules as $r ) {	
					if ( $r->on_dashboard == 0 ) {
						$all_on_dash = false;
						break;
					}		
				}	
		
				$t1 = ( $all_on_dash ) ? '	' : '';
				
				// Structur from the beginning and the end of the rule file
				$first_part = "<?php \n";
				$first_part .= "/***\n";
 				$first_part .= " * Contains the rules for the activation and deactivation of the plugins \n";
 				$first_part .= " *\n";
 				$first_part .= " * @package     Plugin Logic\n";
				$first_part .= " * @author      simon_h\n";
				$first_part .= " * \n";
				$first_part .= " * @change      1.0.4\n";
 				$first_part .= " * @since       1.0.0\n";
 				$first_part .= " */\n\n";
				if ($all_on_dash) $first_part .= "if ( !is_admin() ) {\n\n";
				$first_part .= $t1."function search_needles(\$haystack, \$needles) {\n";
				$first_part .= "\t$t1"."foreach(\$needles as \$needle) {\n";
				$first_part .= "\t\t$t1"."if (strpos(\$haystack, \$needle) !== false) return true;\n";
				$first_part .= "\t$t1"."}\n";
				$first_part .= "\t$t1"."return false;\n";
				$first_part .= $t1."}\n\n";
				$first_part .= $t1."function plugin_logic_rules(\$plugins){ \n";
				$first_part .= "\t$t1"."\$current_url = 'http' . ((!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] == 'on') ? 's://' : '://') . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI']; \n";	
	
				$last_part = "\n";
				$last_part .= "\t$t1"."//Rules for plugin-logic \n";
				if (!$all_on_dash) $last_part .= "\t$t1"."if ( !is_admin() ) { \n";
				$last_part .= "		\$key = array_search( '". plugin_basename( __FILE__ ) ."' , \$plugins );\n";
				$last_part .= "		if ( \$key !== false ) {\n";
				$last_part .= "			unset( \$plugins[\$key] );\n";
				$last_part .= "		}\n";
				if (!$all_on_dash) $last_part .= "\t$t1"."}\n";
				$last_part .= $t1."\n";
				$last_part .= "\t$t1"."return \$plugins; \n";
				$last_part .= $t1."}\n";
				$last_part .= $t1."add_filter( 'option_active_plugins', 'plugin_logic_rules' );\n";
				if ($all_on_dash) $last_part .= "\n} \n";
				
				$rules = '';
				foreach( $singlesite_rules as $r ) 
					$rules .= plugin_logic::rule_syntax($r, $all_on_dash);	
				
				$rule_file_content = $first_part . $rules . $last_part;		
			} 	
			
			return $rule_file_content;
		}
		
		
		/***
		 * Creates the plugin activation/deactivation rules for multisite installations
		 * @since 1.0.4
		 */
		public static function rules_for_mulitsite( $multisite_rules = array() ) {
			$rule_file_content = '';
			if ( !empty($multisite_rules) ) {
		
				// Structur for the beginning of the rule file
				$head = "<?php \n";
				$head .= "/***\n";
 				$head .= " * Contains the rules for the activation and deactivation of the plugins \n";
 				$head .= " *\n";
 				$head .= " * @package     Plugin Logic\n";
				$head .= " * @author      simon_h\n";
				$head .= " * \n";
				$head .= " * @change      1.0.4\n";
 				$head .= " * @since       1.0.0\n";
 				$head .= " */\n\n";
				$head .= "function search_needles(\$haystack, \$needles) {\n";
				$head .= "	foreach(\$needles as \$needle) {\n";
				$head .= "		if (strpos(\$haystack, \$needle) !== false) return true;\n";
				$head .= "	}\n";
				$head .= "	return false;\n";
				$head .= "}\n\n";
				$head .= "\$current_blog_id = get_current_blog_id(); \n";
				$head .= "\$current_url = 'http' . ((!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] == 'on') ? 's://' : '://') . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI']; \n\n\n";	
				
				
				$previos_blog = $multisite_rules[0]->blog_id;
				$rule_file_content = $head;
				
				// Test if there plugins disabled in admin menu
				$all_on_dash = true; 
				$z = 0; 
				while ( ($z < count($multisite_rules) && ($multisite_rules[$z]->blog_id == $previos_blog) ) ) { 
					if ( $multisite_rules[$z]->on_dashboard == 0 ) {
						$all_on_dash = false;
						break; 
					}	
					$z++;
				}	
				
				$t1 = '	';
				$new_rule_head = '';
				if ( $previos_blog == 0 ) {
					if ( $all_on_dash ) 
						$rule_file_content .= "if ( !is_admin() ) {\n\n";
					 else {
						$rule_file_content .= "\n\n";
						$t1 = '';	
					}	
				} else {
					if ( $all_on_dash ) 
						$rule_file_content .= "if ( !is_admin() && (\$current_blog_id == $previos_blog) ) {\n\n";
					else 
						$rule_file_content .= "if ( \$current_blog_id == $previos_blog ) {\n\n";
				}
					
				$rule_file_content .= $t1."function plulo_rules_for_blog_{$multisite_rules[0]->blog_id}(\$plugins) { \n";
				
				$current_rules = '';
				$current_index = 0;
				
				foreach( $multisite_rules as $r ) {				
			
					if ( $previos_blog == $r->blog_id ) 
					{						
						$rule_file_content .= plugin_logic::rule_syntax($r, $all_on_dash);
					} 
					else /**** Rules for another blog found  ****/
					{		
						// Close rules from previos blog
						$t1 = ( ($previos_blog != 0) || (($previos_blog == 0) && ($all_on_dash )) ) ? '	' : '';
						$rule_foot = "\n";
						$rule_foot .= "\t$t1"."return \$plugins; \n";
						$rule_foot .= $t1."}\n";
						if ( ($previos_blog == 0) && !$all_on_dash ) {
							$rule_foot .= "add_filter( 'site_option_active_sitewide_plugins', 'plulo_rules_for_blog_0' );\n";
							$rule_foot .= "\n\n\n"; 
						} elseif ( ($previos_blog == 0) && $all_on_dash ) {
							$rule_foot .= "	add_filter( 'site_option_active_sitewide_plugins', 'plulo_rules_for_blog_0' );\n";
							$rule_foot .= "}\n\n\n"; 
						} else {
							$rule_foot .= "	add_filter( 'option_active_plugins', 'plulo_rules_for_blog_$previos_blog' );\n";
							$rule_foot .= "}\n\n\n"; 
						}
						$rule_file_content .=  $rule_foot; 
						
						/* Rule head for the next blog ******/
						$new_blog = $r->blog_id;

						$all_on_dash = true; 
						$y = $current_index;
						while ( ($y < count($multisite_rules) && ($multisite_rules[$y]->blog_id == $r->blog_id) ) ) { 
							if ( $multisite_rules[$y]->on_dashboard == 0 ) {
								$all_on_dash = false;
								break; 
							}	
							$y++;
						}	
						
						$new_rule_head = '';
						if ( $all_on_dash ) {
							$new_rule_head .= "if ( !is_admin() && (\$current_blog_id == $new_blog) ) {\n\n";
						} else {
							$new_rule_head .= "if ( \$current_blog_id == $new_blog ) {\n\n";
						}
						$new_rule_head .= "	function plulo_rules_for_blog_{$r->blog_id}(\$plugins) { \n";
						
						$rule_file_content .= $new_rule_head . plugin_logic::rule_syntax($r, $all_on_dash);
						
						$previos_blog = $new_blog;
					}
			
					$current_index++;
				}
				
				// Close last rule
				$t1 = ( ($previos_blog != 0) || (($previos_blog == 0) && ($all_on_dash )) ) ? '	' : '';
				$rule_foot = "\n";
				$rule_foot .= "\t$t1"."return \$plugins; \n";
				$rule_foot .= $t1."}\n";
				if ( ($previos_blog == 0) && !$all_on_dash ) {
					$rule_foot .= "add_filter( 'site_option_active_sitewide_plugins', 'plulo_rules_for_blog_0' );\n";
					$rule_foot .= "\n\n\n"; 
				} elseif ( ($previos_blog == 0) && $all_on_dash ) {
					$rule_foot .= "	add_filter( 'site_option_active_sitewide_plugins', 'plulo_rules_for_blog_0' );\n";
					$rule_foot .= "}\n\n\n"; 
				} else {
					$rule_foot .= "	add_filter( 'option_active_plugins', 'plulo_rules_for_blog_$previos_blog' );\n";
					$rule_foot .= "}\n\n\n"; 
				}
				$rule_file_content .=  $rule_foot; 
		
			} 
			
			return $rule_file_content;
		}
		
		
		/***
		 * The create essential rule syntax for a plugin
		 * @since 1.0.4
		 */
		public static function rule_syntax( $r = array(), $all_on_dash = true)  {
			if ( is_multisite() ) {
				$t1 = ( ($r->blog_id != 0) || (($r->blog_id == 0) && ($all_on_dash )) ) ? '	' : '';
				$current_url_str = "\$GLOBALS['current_url']"; 
			} else {
				$t1 = ( $all_on_dash ) ? '	' : ''; 
				$current_url_str = "\$current_url"; 
			}	
					
			$t2 = ( ($r->on_dashboard == 1) && !$all_on_dash  ) ? '	' : '';
			
			$url_rules = unserialize($r->urls);
			$word_rules = unserialize($r->words);

			// Prepare the syntax for the rule-file
			$plugin_name = substr (strrchr ($r->name, '/'), 1);
			if ($plugin_name === false) $plugin_name = $r->name;
			
			$logic_syn = ($r->logic == 0) ? '!' : '';
			$or_syn = ( (count($url_rules) > 0) && (count($word_rules) > 0) )  ?  ' ||' : '';
			
			$url_rules_syn  = array('','');
			if (count($url_rules) > 0) { 
				$url_rules_syn[0] = "\t$t1$t2"."\$url_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$url_rules) ."'\n\t\t$t1$t2); \n";
				$url_rules_syn[1] = "in_array($current_url_str, \$url_rules)";
			}
			
			$word_rules_syn  = array('','');
			if (count($word_rules) > 0) { 
				$word_rules_syn[0] = "\t$t1$t2"."\$word_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$word_rules) ."'\n\t\t$t1$t2); \n";
				$word_rules_syn[1] = "search_needles($current_url_str, \$word_rules)"; 
			}
			
			$essential_rule = "\n";	
			
			if ( ($r->on_dashboard == 1) && !$all_on_dash ) {
				$essential_rule .= "\t$t1"."//Rules for $plugin_name\n";
				$essential_rule .= "\t$t1"."if ( !is_admin() ) {\n";
				$t2 = '	';
			} else {
				$essential_rule  .= "\t$t1"."//Rules for $plugin_name\n";	
			}	

			$essential_rule .= $url_rules_syn[0];
			$essential_rule .= $word_rules_syn[0];
			$essential_rule .= "\t$t1$t2"."if ( $logic_syn($url_rules_syn[1]$or_syn $word_rules_syn[1]) ) { \n";
			
			if ( is_multisite() && ($r->blog_id == 0) ) 
				$essential_rule .= "\t\t$t1$t2"."unset( \$plugins['{$r->name}'] );\n";
			 else {	
				$essential_rule .= "\t\t$t1$t2"."\$key = array_search( '{$r->name}' , \$plugins );\n";
				$essential_rule .= "\t\t$t1$t2"."if ( \$key !== false ) {\n";
				$essential_rule .= "\t\t\t$t1$t2"."unset( \$plugins[\$key] );\n";
				$essential_rule .= "\t\t$t1$t2"."}\n";	
			}	

			$essential_rule .= "\t$t1$t2"."}\n";
			if ( ($r->on_dashboard == 1) && !$all_on_dash ) $essential_rule .= "\t$t1"."}\n";

			return $essential_rule ;
		}
		
			
		/***
		 * Try to write the rule content string into the file WPMU_PLUGIN_DIR/plugin-logic-rules.php
		 * @since 1.0.4
		 */
		public static function write_rule_file( $rules = '' ) {
			$rule_file = WPMU_PLUGIN_DIR . '/' . 'plugin-logic-rules.php';
			if ( $rules != '') {

				// Check directory permissions and write the WPMU_PLUGIN_DIR directory if not exists, 
				if ( !file_exists( WPMU_PLUGIN_DIR ) ) {
					if ( is_writable( WP_CONTENT_DIR ) ) {
						mkdir(WPMU_PLUGIN_DIR, 0755);
					} else {
						$error_in1 = substr(WP_CONTENT_DIR, strlen( ABSPATH ));
						$error_in2 = substr(WPMU_PLUGIN_DIR, strlen( ABSPATH ));
						$write_error = ''; 
						$write_error .= '<div class="update-nag"><p> Your <code>'. $error_in1 .'</code> directory isn&#8217;t <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>.<br>'; 
						$write_error .= 'These are the rules you should write in an PHP-File, create the '. $error_in2 .' directory and place it in the <code>'. $error_in2 .'</code> directory. ';
						$write_error .= 'Click in the field and press <kbd>CTRL + a</kbd> to select all. </p>';
						$write_error .= '<textarea  readonly="readonly" name="rules_txt" rows="7" style="width:100%; padding:11px 15px;">' . $rules . '</textarea></div>';
						$write_error .= '<br><br>'; 
						
						$err_arr[] = $write_error;
						$err_arr[] = $rules;
						return $err_arr;
					}
				} 
				
				// Check directory permissions and write the plugin-logic-rules.php file 
				if ( file_exists( WPMU_PLUGIN_DIR ) ) {
					if ( is_writable( WPMU_PLUGIN_DIR ) ) {
						file_put_contents( $rule_file, $rules );
					} else { 
						$error_in = substr(WPMU_PLUGIN_DIR, strlen( ABSPATH ));
						$write_error = ''; 
						$write_error .= '<div class="update-nag"><p> Your <code>'. $error_in .'</code> directory isn&#8217;t <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>.<br>'; 
						$write_error .= 'These are the rules you should write in an PHP-File and place it in the <code>'. $error_in .'</code> directory. ';
						$write_error .= 'Click in the field and press <kbd>CTRL + a</kbd> to select all. </p>';
						$write_error .= '<textarea  readonly="readonly" name="rules_txt" rows="7" style="width:100%; padding:11px 15px;">' . $rules . '</textarea></div>';
						$write_error .= '<br><br>'; 
						
						$err_arr[] = $write_error;
						$err_arr[] = $rules;
						return $err_arr;
					}		
				}	
					
			} elseif ( file_exists( $rule_file ) )  {
				if ( !unlink( $rule_file ) )  {
					wp_die( wp_sprintf( 
								'Error cannot delete the old rule file: <code>' . substr($rule_file, strlen( ABSPATH )) .'</code>'.
								' The directory isn&#8217;t writable.'
							) 
					);
				}
			}	
			
			return false;
		}
		
			
		/***
		 * If the database table for Plugin Logic rules doesn't exists, create it
		 * @since 1.0.4
		 */
		public static function create_databse_table() {
			global $wpdb;	
			
			$charset_collate = '';
			if ( ! empty( $wpdb->charset ) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty( $wpdb->collate ) )
				$charset_collate .= " COLLATE $wpdb->collate";

			$blog_id_col = ( is_multisite() ) ? "blog_id bigint(20) NOT NULL, " : '';
			
			$wpdb->query( 
				"CREATE TABLE IF NOT EXISTS ". PLULO_DBTABLE ." (
					$blog_id_col
					name longtext NOT NULL,
					on_dashboard tinyint(2) NOT NULL,
					logic tinyint(2) NOT NULL,
					urls longtext NOT NULL,
					words longtext NOT NULL
				) $charset_collate;" 
			);	
			
		}
	
	
		/***
		 * Actions if user activate Plugin Logic:
		 * If database table with rules exists, try create to create the rule file
		 * 
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public static function on_activation() {
			if ( is_multisite() && !is_network_admin() ) { 
				$sitewide_plugs_page = get_bloginfo('url') . '/wp-admin/network/plugins.php';
				wp_die( sprintf( __('To activate this plugin go to the <a href="%s">network admin page</a>','plugin-logic'), $sitewide_plugs_page ) );
			}
			
			plugin_logic::create_databse_table();
				
			// Get previous saved data from database if table exists
			$order = ( is_multisite() ) ? "blog_id" : "name";
			$rules_from_database = $GLOBALS['wpdb']->get_results( "SELECT * FROM ". PLULO_DBTABLE ." ORDER BY $order ASC" ); 
			
			if ( count($rules_from_database) > 0 ) {
				
				$write_error = false;
				if ( is_multisite() ) {
					$content = plugin_logic::rules_for_mulitsite( $rules_from_database );
					$write_error = plugin_logic::write_rule_file( $content );
				} else {
					$content = plugin_logic::rules_for_singlesite( $rules_from_database );
					$write_error = plugin_logic::write_rule_file( $content );
				}	

				$rule_file = WPMU_PLUGIN_DIR . '/' . 'plugin-logic-rules.php';
				if ( (is_array($write_error)) && !file_exists($rule_file) ) {
					$tmp_rule_file = __DIR__ . '/' . 'plugin-logic-rules.php';
					file_put_contents( $tmp_rule_file, $write_error[1] );
					trigger_error(
						"<br>Cannot create the rule file: <code>" . substr($rule_file, strlen( ABSPATH )) ."</code> The directory isn&#8217;t writeable. <br> 
						Please put the <code>" . substr($tmp_rule_file , strlen( ABSPATH )) . "</code> in to the <code>" . 
						substr(WPMU_PLUGIN_DIR , strlen( ABSPATH )) . "</code> directory and try to activate the plugin again. <br>", E_USER_ERROR);	
				}
				
			} 
			
		}
		
		/***
		 * Actions if user deactivate Plugin Logic:
		 * Delete the rule file in the WPMU_PLUGIN_DIR and if the directory is empty also delete them
		 * @since 1.0.0
		 */
		public function on_deactivation() {
			$rule_file = WPMU_PLUGIN_DIR . '/' . 'plugin-logic-rules.php';
			if ( file_exists($rule_file) ) {
				if ( !unlink($rule_file) ) {
					trigger_error("Cannot delete the old rule file: <code>" . 
						substr($rule_file, strlen( ABSPATH )) ."</code>", E_USER_ERROR);
				}	
			}
			
			if ( file_exists( WPMU_PLUGIN_DIR ) )  {
				if ( count(scandir(WPMU_PLUGIN_DIR) <= 2) ) rmdir( WPMU_PLUGIN_DIR );
			}
		}	
		
		/***
		 * Actions if user uninstall Plugin Logic:
		 * Delete database entries
		 * 
		 * @since 1.0.0
		 * @change 1.0.4	
		 */
		public function on_uninstall() {
			$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS " . PLULO_DBTABLE );
			delete_option( 'plulo_on_dash_col' ); 	
			if ( is_multisite() ) delete_option( 'plulo_blog_range' ); 	
		}	
		
		
	} // end class
	
} // end if class exists
