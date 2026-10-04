<?php
/** Delete only this plugin's configuration. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'srp_settings' );
