<?php
/**
 * Uninstall cleanup for Aspen Smart Links.
 *
 * @package AspenSmartLinks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin-local user tag tracking.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'aspen_smart_links_tags'
	)
);

