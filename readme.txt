=== Plugin Logic ===
Contributors: simon_h
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SMX67F2CZLDZL
Tags: deactivate plugins by url, activate plugins by url, deactivate plugins by rules, disable plugins by page, disable plugins by rules 
Requires at least: 3.8.0
Tested up to: 3.8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Loading plugins on pages only if they are really needed.

== Description ==

There are many possibilities to increase the speed of your Wordpress Page.  
One of them, which is often forgotten, is to deactivate Plugins on pages, where they are not needed. 
This Plugin allows you to do this on a very easy way. 
So you can reduce the amount of JavaScript and CSS files which are loaded and SQL queries run at page load.

== Installation ==

1. Install the Plugin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create your own rules on the Plugin Logic options page

== Frequently Asked Questions ==

**Q. How do I format my rules?**

Use "http://" or "https://" at the beginning to define urls. 
Words without these keywords at the beginning are interpreted as normal words. They will be searched in the requested url.
To separate your rules use the comma sign.

**Q. Will this work on a multisite installation?**

It has not been tested with a multisite installation yet.

== Screenshots ==

1. The Options Page.
2. Options for the Behavior on Dashbord.

== Changelog ==

= 1.0.0 =
* Initial release