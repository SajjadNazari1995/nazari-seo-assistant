<?php
/**
 * Plugin Name:       SEO Assistant for Elementor
 * Description:       Right-click any section in the Elementor editor and get a clear, ordered review of its HTML tags, image sizes and resolution, mobile fit and loading speed, with a score and a one-click re-check.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Requires Plugins:  elementor
 * Author:            Nazari Technologies
 * Author URI:        https://github.com/SajjadNazari1995
 * Text Domain:       seo-assistant-for-elementor
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'NZSA_VERSION', '1.1.0' );
define( 'NZSA_FILE', __FILE__ );
define( 'NZSA_PATH', plugin_dir_path( __FILE__ ) );
define( 'NZSA_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'SEO Assistant needs PHP %1$s or newer. Your server runs PHP %2$s, so the plugin is switched off.', 'seo-assistant-for-elementor' ),
					'8.2',
					PHP_VERSION
				)
			) . '</p></div>';
		}
	);
	return;
}

require_once NZSA_PATH . 'includes/class-nzsa-plugin.php';

add_action( 'plugins_loaded', array( 'NZSA_Plugin', 'instance' ), 20 );
