<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin: analysis engine, REST endpoint, admin pages and the Elementor editor window.
 */
final class NZSA_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {
		foreach ( array(
			'class-nzsa-types',
			'class-nzsa-settings',
			'class-nzsa-rule',
			'class-nzsa-images',
			'class-nzsa-tree',
			'class-nzsa-context',
			'rules/class-nzsa-image-refs',
			'rules/class-nzsa-rule-tags',
			'rules/class-nzsa-rule-images',
			'rules/class-nzsa-rule-weight',
			'rules/class-nzsa-rule-responsive',
			'rules/class-nzsa-rule-links',
			'class-nzsa-analyzer',
			'class-nzsa-rest',
		) as $file ) {
			require_once NZSA_PATH . 'includes/' . $file . '.php';
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );

		NZSA_Rest::init();

		if ( is_admin() ) {
			require_once NZSA_PATH . 'includes/class-nzsa-admin.php';
			NZSA_Admin::init();
		}

		require_once NZSA_PATH . 'includes/class-nzsa-editor.php';
		NZSA_Editor::init();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'nazari-seo-assistant', false, dirname( plugin_basename( NZSA_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}
}
