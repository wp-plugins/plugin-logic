<?php
/***
 * Plugin Name: Plugin Logic
 * Plugin URI: http://wordpress.org/plugins/plugin-logic/
 * Description: Activate plugins on pages only if they are really needed.  
 * Author: simon_h
 * Version: 1.0.3
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
		  * Init class properties and methods via hook; 
		  * 
		  * @since 1.0.0
		  * @change 1.0.2
		  */
		public function __construct() {
			$this->on_dash_columm = get_option( 'plulo_on_dash_col', '');
			$this->plugin_base = plugin_basename( __FILE__ );
			
			register_deactivation_hook( __FILE__, array( $this, 'on_deactivation' ) );
			register_uninstall_hook( __FILE__,    array( 'plugin_logic', 'on_uninstall' ) );
			
			load_plugin_textdomain( 'plugin-logic', false, dirname(plugin_basename(__FILE__)) . '/I18n/' );
			add_filter( "plugin_action_links_$this->plugin_base", array( $this, 'plugin_add_settings_link' ) );
			add_action( 'admin_menu', array( $this,'on_admin_menu') );
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
			add_action("load-$this->pagehook", array( $this,'register_screen_options_wrap' ));
		}
		
		
		/***
		 * Register a new screen-options-wrap and add custom HTML to the screen-options-wrap panel.
		 * @since 1.0.0
		 */
		public function register_screen_options_wrap(){
			$screen = get_current_screen(); 
			 
			if(!is_object($screen) || $screen->id != $this->pagehook) return;
			 
			$screen->add_option('my_option', ''); 
			add_filter('screen_layout_columns', array( $this,'screen_options_controls') ); 
		}
		
		
		/***
		 * Check if screen-options-wrap controls changed and output the html-code for the controls.
		 * 
		 * @since 1.0.0
		 * @change 1.0.2
		 */
		public function screen_options_controls(){

			if( isset($_POST['plulo_toggle_dash_col'])  ) { 
				if (!is_user_logged_in() || !current_user_can('activate_plugins') )
					wp_die( __('Cheatin&#8217; uh?') );		
					
				$this->on_dash_columm = $_POST['plulo_toggle_dash_col'];

				// Update the Database with the new on dashboard behavior option
				if ( get_option( 'plulo_on_dash_col' ) !== false ) {
					update_option( 'plulo_on_dash_col', $this->on_dash_columm );
				} else {
					add_option( 'plulo_on_dash_col', $this->on_dash_columm, null, '');
				}	
			}
			
			?>
			<div style="padding:15px 0 0 15px;">
				<form action="" method="post">
					<input name="plulo_toggle_dash_col" type="hidden" value=""/>
					<input name="plulo_toggle_dash_col" type="checkbox" value="checked" onChange="this.form.submit()" <?php echo $this->on_dash_columm; ?> /> 
						<?php _e('Show Options for Behavoir on Dashboard', 'plugin-logic') ?>
				</form>
			</div>
			<?php
		}
		
		
		 /***
		  * Plugin Logic options page for the Dashboard
		  * 
		  * @since 1.0.0
		  * @change 1.0.2
		  */
		public function plulo_option_page() {
			global $wpdb;	
			$db_table = $wpdb->base_prefix . 'plugin_logic';
			$write_error = '';
			$all_on_dash = false;
			
			// Action if Save-Button pressed 
			if( isset($_POST['plulo_btn1']) ) {
				if (!is_user_logged_in() || !current_user_can('activate_plugins') )
					wp_die( __('Cheatin&#8217; uh?') );		
			
				$plugin_list = get_option( 'active_plugins', array () );
				
				// Load data from db
				$old_db_list = array();
				if ( $wpdb->get_var( "SHOW TABLE STATUS LIKE '$db_table'") ) {
					$old_db_list = $wpdb->get_results( "SELECT name, on_dashboard FROM $db_table ORDER BY name ASC" );
				} 
				// Filter inactive Plugins with rules and add it to the $plugin_list
				$no_dashboard_plugs = array();
				$old_on_dashboard_opt = array();
				foreach($old_db_list as $db_pl) {
					if ( ! in_array($db_pl->name, $plugin_list ) ) {
						$no_dashboard_plugs[] = $db_pl->name;
					}
					$old_on_dashboard_opt[] = $db_pl->on_dashboard; 
				}
				if ( count($no_dashboard_plugs ) > 0 ) {
					$plugin_list = array_merge($plugin_list, $no_dashboard_plugs);
					sort($plugin_list);
				}	
					
				// Get user input			
				$check_array = array();
				if (isset($_POST['plcon_checklist'])) {
					// Check-Button-List with dashboard bevavior options
					$check_array = $_POST['plcon_checklist'];
				}
				
				$t1 = '';
				if ( !in_array('0',$check_array ) && ($this->on_dash_columm == 'checked') ) { 
					$all_on_dash = true;
					$t1 = '	';
				} elseif( !in_array('0',$old_on_dashboard_opt) && ($this->on_dash_columm == '') ) {
					$all_on_dash = true;
					$t1 = '	';
				}	
				
				$radio_array = array();
				if (isset($_POST['plcon_radiolist'])) {
					// Radio-Button-List with logic options
					$radio_array = $_POST['plcon_radiolist'];
				}
				
				$user_txt_input = array();
				if (isset($_POST['plcon_txt_list'])) {
					// User rules as textinput
					$user_txt_input = $_POST['plcon_txt_list'];
				}
			
				// Save new values to DB and create activation/deactivation rules 
				$z = 0;	 
				$plugin_rules = '';
				foreach ($plugin_list as $path) {
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
					
						if ($this->on_dash_columm == 'checked') {						
							$on_dashboard = $check_array[$z];
						} else {
							$on_dashboard = $wpdb->get_var( "SELECT on_dashboard FROM $db_table WHERE name Like '$path'" );
							if ( $on_dashboard == NULL ) $on_dashboard = '1'; 
						}	
							
						$logic = $radio_array[$z];
					
						//Database updates 
						$db_row_exists = $wpdb->get_var( "SELECT name FROM $db_table WHERE name Like '$path'" );
						if ( $db_row_exists != NULL ) {
							$wpdb->update( $db_table, array('name' => $path ,
													'on_dashboard' => $on_dashboard,
													'logic' => $logic, 
													'urls' => serialize($url_rules), 
													'words' => serialize($word_rules) 
													), array('name' => $path)
											);
						} else {
							$wpdb->insert( $db_table, array('name' => $path ,
													'on_dashboard' => $on_dashboard,
													'logic' => $logic, 
													'urls' => serialize($url_rules), 
													'words' => serialize($word_rules) 
													)
											);
						}						
												
						// Prevent reactivation bug
						if ( ($on_dashboard == 1) && !is_plugin_active($path) ) {
							require_once(ABSPATH .'/wp-admin/includes/plugin.php');
							activate_plugin($path);
						}

						// Prepare the syntax for the rule-file
						$plugin_name = substr (strrchr ($path, '/'), 1);
						if ($plugin_name === false) $plugin_name = $path;

						($logic == 0) ?  $logic_syn = '!' : $logic_syn = '';
						( (count($url_rules) > 0) && (count($word_rules) > 0) )  ?  $or_syn = ' ||' : $or_syn = '';
					
						( ($on_dashboard == 1) && !$all_on_dash ) ? $t2 = '	' : $t2 = '';
						
						$url_rules_syn  = array('','');
						if (count($url_rules) > 0) { 
							$url_rules_syn[0] = "\t$t1$t2"."\$url_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$url_rules) ."'\n\t\t$t1$t2); \n";
							$url_rules_syn[1] = "in_array(\$current_url, \$url_rules)";
						}
						
						$word_rules_syn  = array('','');
						if (count($word_rules) > 0) { 
							$word_rules_syn[0] = "\t$t1$t2"."\$word_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$word_rules) ."'\n\t\t$t1$t2); \n";
							$word_rules_syn[1] = "search_needles(\$current_url, \$word_rules)"; 
						}
						
						$plugin_rules .= "\n";						
						if ( ($on_dashboard == 1) && !$all_on_dash ) {
							$plugin_rules .= "\t"."//Rules for $plugin_name\n";
							$plugin_rules .= "	if ( !is_admin() ) {\n";
						} else {
							$plugin_rules .= "\t$t1"."//Rules for $plugin_name\n";	
						}
						$plugin_rules .= $url_rules_syn[0];
						$plugin_rules .= $word_rules_syn[0];
						$plugin_rules .= "\t$t1$t2"."if ( $logic_syn($url_rules_syn[1]$or_syn $word_rules_syn[1]) ) { \n";
						$plugin_rules .= "\t\t$t1$t2"."\$key = array_search( '$path' , \$plugins );\n";
						$plugin_rules .= "\t\t$t1$t2"."if ( \$key !== false ) {\n";
						$plugin_rules .= "\t\t\t$t1$t2"."unset( \$plugins[\$key] );\n";
						$plugin_rules .= "\t\t$t1$t2"."}\n";
						$plugin_rules .= "\t$t1$t2"."}\n";
						if ( ($on_dashboard == 1) && !$all_on_dash ) $plugin_rules .= "	}\n";
					} else {
						$wpdb->delete( $db_table, array('name' => $path) );	
					}
					
					$z++;
				}
				
				$write_error = $this->create_rule_file($plugin_rules, $all_on_dash);
				
				// Second site refresh to get the new Plugin status
				if ( !$all_on_dash && !is_array($write_error) ) $this->reload_with_ajax(); 
			}
			
			require 'plugin-logic-table.php';
			$plulo_table = new plulo_table( $this->plugin_base, $all_on_dash );		
			
			?>		
			<!-- Plugin Logic options table -->		
			<div class="wrap">
				<h2>Plugin Logic</h2> <br>
				<form action="" method="post">
					<?php if (is_array($write_error))  echo $write_error[0]; ?> 
					<?php echo $plulo_table->html_output; ?>
					<div id="tfoot" style="margin-top:10px">
						<input name="plulo_btn1" type="submit" value="<?php _e( 'Save Changes' )?>" class="button-primary"/>
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
		 * Creates the plugin activation/deactivation rules-file in the WPMU_PLUGIN_DIR
		 * @since 1.0.0
		 */
		public static function create_rule_file( $rules = '', $all_on_dash = true) {
			$rule_file = WPMU_PLUGIN_DIR . '/' . 'plugin-logic-rules.php';
			if ( $rules != '') {
			
				// TAB-Generator 
				if ( $all_on_dash ){
					function t($count = 3) {
						return str_repeat('	', $count + 1); 
					}
				} else {
					function t($count = 3) {
						return str_repeat('	', $count); 
					}
				}
				
				// Structur from the beginning and the end of the rule file
				$first_part = "<?php \n";
				$first_part .= "/***\n";
 				$first_part .= " * Contains the rules for the activation and deactivation of the plugins \n";
 				$first_part .= " *\n";
 				$first_part .= " * @package	    Plugin Logic\n";
				$first_part .= " * @author      simon_h\n";
 				$first_part .= " * @since       1.0.0\n";
 				$first_part .= " */\n\n";
				if ($all_on_dash) $first_part .= "if ( !is_admin() ) { \n\n";
				$first_part .= t(0)."function search_needles(\$haystack, \$needles) {\n";
				$first_part .= t(1)."foreach(\$needles as \$needle) {\n";
				$first_part .= t(2)."if (strpos(\$haystack, \$needle) !== false) return true;\n";
				$first_part .= t(1)."}\n";
				$first_part .= t(1)."return false;\n";
				$first_part .= t(0)."}\n\n";
				$first_part .= t(0)."function plugin_logic_rules(\$plugins){ \n";
				$first_part .= t(1)."\$current_url = 'http' . ((!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] == 'on') ? 's://' : '://') . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI']; \n";	
				
				$last_part = "\n";
				$last_part .= t(1)."//Rules for plugin-logic \n";
				if (!$all_on_dash) $last_part .= t(1)."if ( !is_admin() ) { \n";
				$last_part .= "		\$key = array_search( '". plugin_basename( __FILE__ ) ."' , \$plugins );\n";
				$last_part .= "		if ( \$key !== false ) {\n";
				$last_part .= "			unset( \$plugins[\$key] );\n";
				$last_part .= "		}\n";
				if (!$all_on_dash) $last_part .= t(1)."}\n";
				$last_part .= t(0)."\n";
				$last_part .= t(1)."return \$plugins; \n";
				$last_part .= t(0)."}\n";
				$last_part .= t(0)."add_filter( 'option_active_plugins', 'plugin_logic_rules' );\n";
				if ($all_on_dash) $last_part .= "\n} \n";

				//Create the rule file WPMU_PLUGIN_DIR/plugin-logic-rules.php if it is possible
				if ( !file_exists( WPMU_PLUGIN_DIR ) ) {
					if ( is_writable( WP_CONTENT_DIR ) ) {
						mkdir(WPMU_PLUGIN_DIR, 0750);
					} else {
						$error_in1 = substr(WP_CONTENT_DIR, strlen( ABSPATH ));
						$error_in2 = substr(WPMU_PLUGIN_DIR, strlen( ABSPATH ));
						$write_error = ''; 
						$write_error .= '<div class="update-nag"><p> Your <code>'. $error_in1 .'</code> directory isn&#8217;t <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>.<br>'; 
						$write_error .= 'These are the rules you should write in an PHP-File, create the '. $error_in2 .' directory and place it in the <code>'. $error_in2 .'</code> directory. ';
						$write_error .= 'Click in the field and press <kbd>CTRL + a</kbd> to select all. </p>';
						$write_error .= '<textarea  readonly="readonly" name="rules_txt" rows="7" style="width:100%; padding:11px 15px;">' . $first_part . $rules . $last_part . '</textarea></div>';
						$write_error .= '<br><br>'; 
						
						$err_arr[] = $write_error;
						$err_arr[] = $first_part . $rules . $last_part;
						return $err_arr;
					}
				} 
				
				if ( file_exists( WPMU_PLUGIN_DIR ) ) {
					if ( is_writable( WPMU_PLUGIN_DIR ) ) {
						file_put_contents( $rule_file, $first_part . $rules . $last_part );
					} else { 
						$error_in = substr(WPMU_PLUGIN_DIR, strlen( ABSPATH ));
						$write_error = ''; 
						$write_error .= '<div class="update-nag"><p> Your <code>'. $error_in .'</code> directory isn&#8217;t <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>.<br>'; 
						$write_error .= 'These are the rules you should write in an PHP-File and place it in the <code>'. $error_in .'</code> directory. ';
						$write_error .= 'Click in the field and press <kbd>CTRL + a</kbd> to select all. </p>';
						$write_error .= '<textarea  readonly="readonly" name="rules_txt" rows="7" style="width:100%; padding:11px 15px;">' . $first_part . $rules . $last_part . '</textarea></div>';
						$write_error .= '<br><br>'; 
						
						$err_arr[] = $write_error;
						$err_arr[] = $first_part . $rules . $last_part;
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
		 * If database table with rules exists, try create to create the rule file
		 * 
		 * @since 1.0.0
		 * @change 1.0.3
		 */
		public static function on_activation() {
			global $wpdb;
			$db_table  = $wpdb->base_prefix . 'plugin_logic';			
			
			// Create table structur
			$charset_collate = '';
			if ( ! empty( $wpdb->charset ) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty( $wpdb->collate ) )
				$charset_collate .= " COLLATE $wpdb->collate";
			
			$wpdb->query( 
				"CREATE TABLE IF NOT EXISTS $db_table (
					name longtext NOT NULL,
					on_dashboard mediumint(9) NOT NULL,
					logic mediumint(9) NOT NULL,
					urls longtext NOT NULL,
					words longtext NOT NULL
				) $charset_collate;" 
			);
			
			// Get previous saved data from database if table exists
			$db_pl_list = array();
			$plugin_rules = '';
			if ( $wpdb->get_var( "SHOW TABLE STATUS LIKE '$db_table'") ) {
				$db_pl_list = $wpdb->get_results( "SELECT name, on_dashboard, logic, urls, words FROM $db_table ORDER BY name ASC" );
				
				$on_dashboard_opt = array();
				foreach($db_pl_list as $db_pl) {
					$on_dashboard_opt[] = $db_pl->on_dashboard;
				}
				
				$all_on_dash = false;
				$t1 = '';
				if ( !in_array('0',$on_dashboard_opt ) ) {
					$all_on_dash = true;
					$t1 = '	';
				}
						
				foreach ($db_pl_list as $db_pl) {
					$path = $db_pl->name;
					$on_dashboard = $db_pl->on_dashboard;
					$logic = $db_pl->logic;
					$url_rules = unserialize($db_pl->urls);
					$word_rules = unserialize($db_pl->words);

					// Prepare the syntax for the rule-file
					$plugin_name = substr (strrchr ($path, '/'), 1);
					if ($plugin_name === false) $plugin_name = $path;
					
					($logic == 0) ?  $logic_syn = '!' : $logic_syn = '';
					( (count($url_rules) > 0) && (count($word_rules) > 0) )  ?  $or_syn = ' ||' : $or_syn = '';
					
						( ($on_dashboard == 1) && !$all_on_dash ) ? $t2 = '	' : $t2 = '';
						
						$url_rules_syn  = array('','');
						if (count($url_rules) > 0) { 
							$url_rules_syn[0] = "\t$t1$t2"."\$url_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$url_rules) ."'\n\t\t$t1$t2); \n";
							$url_rules_syn[1] = "in_array(\$current_url, \$url_rules)";
						}
						
						$word_rules_syn  = array('','');
						if (count($word_rules) > 0) { 
							$word_rules_syn[0] = "\t$t1$t2"."\$word_rules = array (\n\t\t\t$t1$t2'". implode("',\n\t\t\t$t1$t2'",$word_rules) ."'\n\t\t$t1$t2); \n";
							$word_rules_syn[1] = "search_needles(\$current_url, \$word_rules)"; 
						}
						
						$plugin_rules .= "\n";						
						if ( ($on_dashboard == 1) && !$all_on_dash ) {
							$plugin_rules .= "\t"."//Rules for $plugin_name\n";
							$plugin_rules .= "	if ( !is_admin() ) {\n";
						} else {
							$plugin_rules .= "\t$t1"."//Rules for $plugin_name\n";	
						}
						$plugin_rules .= $url_rules_syn[0];
						$plugin_rules .= $word_rules_syn[0];
						$plugin_rules .= "\t$t1$t2"."if ( $logic_syn($url_rules_syn[1]$or_syn $word_rules_syn[1]) ) { \n";
						$plugin_rules .= "\t\t$t1$t2"."\$key = array_search( '$path' , \$plugins );\n";
						$plugin_rules .= "\t\t$t1$t2"."if ( \$key !== false ) {\n";
						$plugin_rules .= "\t\t\t$t1$t2"."unset( \$plugins[\$key] );\n";
						$plugin_rules .= "\t\t$t1$t2"."}\n";
						$plugin_rules .= "\t$t1$t2"."}\n";
						if ( ($on_dashboard == 1) && !$all_on_dash ) $plugin_rules .= "	}\n";
				}
					
				$write_error = plugin_logic::create_rule_file($plugin_rules, $all_on_dash);	
				
				$rule_file = WPMU_PLUGIN_DIR . '/' . 'plugin-logic-rules.php';
				if (  (is_array($write_error)) && !file_exists($rule_file) ) {
					$tmp_rule_file = __DIR__ . '/' . 'plugin-logic-rules.php';
					file_put_contents( $tmp_rule_file, $write_error[1] );
					trigger_error(
						"<br>Cannot create the rule file: <code>" . substr($rule_file, strlen( ABSPATH )) ."</code> The directory isn&#8217;t writeable. <br> 
						Please put the " . substr($tmp_rule_file , strlen( ABSPATH )) . " in to the " . 
						substr(WPMU_PLUGIN_DIR , strlen( ABSPATH )) . ' directory and try to activate the plugin again. <br>', E_USER_ERROR);	
				}
			} 
			
		}
		
		/***
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
		 * Delete database entries
		 * 
		 * @since 1.0.0
		 * @change 1.0.1	
		 */
		public function on_uninstall() {
			$db_table = $GLOBALS['wpdb']->base_prefix . 'plugin_logic';
			$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS " . $db_table );
			delete_option( 'plulo_on_dash_col' ); 		
		}	
		
		
	} // end class
	
} // end if class exists
