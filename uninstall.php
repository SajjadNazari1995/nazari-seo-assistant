<?php
/**
 * Removes plugin data on uninstall, only if the user asked for it.
 *
 * @package nazari-seo-assistant
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$nzsa_settings = get_option( 'nzsa_settings', array() );
if ( is_array( $nzsa_settings ) && ! empty( $nzsa_settings['delete_on_uninstall'] ) ) {
	delete_option( 'nzsa_settings' );
}
