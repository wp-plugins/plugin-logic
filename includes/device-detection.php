<?php	
/***
 * Detect mobile devices
 *
 * @package	    Plugin Logic
 * @since       1.0.5
 */
 
// Security check
if ( ! class_exists('WP') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

/***
 * Based on jamesmehorter's function detect_users_device () from the "Device Theme Switcher" plugin.
 * Detect the user's device by using the MobileESP library written by Anthony Hand [http://blog.mobileesp.com/].
 * Return the string name of their device.
 *
 * @uses     uagent_info
 * @param    void
 * @return   string  The current user's device in one of four options:
 *                      active, handheld, tablet, low_support
 */
 function detect_users_device() {
	//Default is active 
	$device = 'active';

	// Check for Varnish Device Detect: https://github.com/varnish/varnish-devicedetect/
	// Thanks to Tim Broder for this addition! https://github.com/broderboy | http://timbroder.com/
	$http_xua_handheld_devices = array(
		'mobile-iphone',
		'mobile-android',
		'mobile-firefoxos',
		'mobile-smartphone',
		'mobile-generic'
	);
	$http_xua_tablet_devices = array(
		'tablet-ipad',
		'tablet-android'
	);

	// Determine if the HTTP X UA server variable is present
	if ( isset( $_SERVER['HTTP_X_UA_DEVICE'] ) ) {

		// if it is, determine which device type is being used
		if ( in_array( $_SERVER['HTTP_X_UA_DEVICE'], $http_xua_handheld_devices ) ) {
			$device = 'handheld' ;
		} elseif ( in_array( $_SERVER['HTTP_X_UA_DEVICE'], $http_xua_tablet_devices ) ) {
			$device = 'tablet' ;
		}
		
	} else { // DEFAULT ACTION - Use MobileESP to sniff the UserAgent string

		// Include the MobileESP code library for acertaining device user agents
		require_once 'mobile-esp.php';

		// Setup the MobileESP Class
		$ua = new uagent_info;

		// Detect if the device is a handheld
		if ( $ua->DetectSmartphone() || $ua->DetectTierRichCss() ) {
			$device = 'handheld' ;
		}

		// Detect if the device is a tablet
		if ( $ua->DetectTierTablet() || $ua->DetectKindle() || $ua->DetectAmazonSilk() ) {
			$device = 'tablet' ;
		}

		// Detect if the device is a low_support device (poor javascript and css support / text-only)
		if ( $ua->DetectBlackBerryLow() || $ua->DetectTierOtherPhones() ) {
			$device = 'low_support';
		}
	}

	// Return the user's device
	return $device ;
} 
