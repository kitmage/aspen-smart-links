<?php
/**
 * Plugin Name: Aspen Smart Links
 * Description: Adds a shortcode button that applies/removes a FluentCRM tag and optionally opens an external URL in a new tab.
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Aspen
 * License: GPLv2 or later
 * Text Domain: aspen-smart-links
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASPEN_SMART_LINKS_VERSION' ) ) {
	define( 'ASPEN_SMART_LINKS_VERSION', '1.0.2' );
}
if ( ! defined( 'ASPEN_SMART_LINKS_PLUGIN_FILE' ) ) {
	define( 'ASPEN_SMART_LINKS_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'ASPEN_SMART_LINKS_PLUGIN_DIR' ) ) {
	define( 'ASPEN_SMART_LINKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ASPEN_SMART_LINKS_PLUGIN_URL' ) ) {
	define( 'ASPEN_SMART_LINKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once ASPEN_SMART_LINKS_PLUGIN_DIR . 'includes/class-aspen-smart-links.php';

Aspen_Smart_Links::instance();
