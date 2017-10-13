<?php
namespace Stock;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.p
 *
 * @link              https://github.com/francisw/soup-release
 * @since             4.8.1
 * @package           soup_release
 *
 * @wordpress-plugin
 * Plugin Name:       Vacation Soup Stock Installer
 * Plugin URI:        https://github.com/francisw/soup-release/release
 * Description:       Backup Stock for Distribution
 * Version:           0.0.1
 * Author:            Francis Wallinger
 * Author URI:        http://github.com/francisw
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */
// If this file is accessed directory, then abort.
if ( ! defined( 'WPINC' ) ) {
	throw new \Exception("Cannot be accessed directly");
}
if ( ! function_exists( 'version_compare' ) || version_compare( PHP_VERSION, '5.3.0', '<' ) ) {
	throw new \Exception("PHP Version not supported");
}

// Now check he required plugins

require_once("inc/autoloader.php");

add_action("admin_init",array(SoupStock::single(),'init'));
