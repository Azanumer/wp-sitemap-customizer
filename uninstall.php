<?php
/**
 * Uninstall handler for Sitemap Customizer.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wsc_settings' );
