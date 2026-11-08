<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Loads the review window into the Elementor editor.
 */
final class NZSA_Editor {

	public static function init(): void {
		add_action( 'elementor/editor/after_enqueue_scripts', array( self::class, 'scripts' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( self::class, 'styles' ) );
	}

	public static function styles(): void {
		wp_enqueue_style( 'nzsa-editor', NZSA_URL . 'assets/editor.css', array(), NZSA_VERSION );
	}

	public static function scripts(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_enqueue_script( 'nzsa-editor', NZSA_URL . 'assets/editor.js', array( 'jquery' ), NZSA_VERSION, true );
		wp_localize_script( 'nzsa-editor', 'NZSA_EDITOR', self::config() );
	}

	/**
	 * @return array<int,array{key:string,label:string}>
	 */
	private static function roles(): array {
		$list = array();
		foreach ( NZSA_Roles::labels() as $key => $label ) {
			$list[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		return $list;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function config(): array {
		return array(
			'endpoint' => esc_url_raw( rest_url( NZSA_Rest::NAMESPACE . '/analyze' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'roles'    => self::roles(),
			'i18n'     => array(
				'menu'        => __( 'Review structure', 'seo-assistant-for-elementor' ),
				'title'       => __( 'Structure review', 'seo-assistant-for-elementor' ),
				'recheck'     => __( 'Check again', 'seo-assistant-for-elementor' ),
				'close'       => __( 'Close', 'seo-assistant-for-elementor' ),
				'checking'    => __( 'Checking…', 'seo-assistant-for-elementor' ),
				'ok'          => __( 'No problems found here.', 'seo-assistant-for-elementor' ),
				'summary'     => __( 'Summary', 'seo-assistant-for-elementor' ),
				'score'       => __( 'Score', 'seo-assistant-for-elementor' ),
				'outOf'       => __( 'out of', 'seo-assistant-for-elementor' ),
				'priorities'  => __( 'Fix these first', 'seo-assistant-for-elementor' ),
				'nothingTop'  => __( 'Nothing urgent. Great work!', 'seo-assistant-for-elementor' ),
				'locate'      => __( 'Show in editor', 'seo-assistant-for-elementor' ),
				'error'       => __( 'The review could not be completed. Please try again.', 'seo-assistant-for-elementor' ),
				/* translators: 1: number of elements, 2: number of images, 3: image weight in KB */
				'stats'       => __( 'Elements: %1$d · Images: %2$d · Image weight: %3$d KB', 'seo-assistant-for-elementor' ),
				'fix'         => __( 'What to do', 'seo-assistant-for-elementor' ),
				'section'     => __( 'Section', 'seo-assistant-for-elementor' ),
				'container'   => __( 'Container', 'seo-assistant-for-elementor' ),
				'column'      => __( 'Column', 'seo-assistant-for-elementor' ),
				'widget'      => __( 'Widget', 'seo-assistant-for-elementor' ),
				'excellent'   => __( 'Excellent', 'seo-assistant-for-elementor' ),
				'good'        => __( 'Good', 'seo-assistant-for-elementor' ),
				'needsWork'   => __( 'Needs work', 'seo-assistant-for-elementor' ),
				'poor'        => __( 'Poor', 'seo-assistant-for-elementor' ),
				'scopeElement' => __( 'This element', 'seo-assistant-for-elementor' ),
				'scopePage'   => __( 'Whole page', 'seo-assistant-for-elementor' ),
				'showAll'     => __( 'Show elements without problems', 'seo-assistant-for-elementor' ),
				'role'        => __( 'This page is a', 'seo-assistant-for-elementor' ),
				/* translators: %s: detected page role, for example "Home page" */
				'roleAuto'    => __( 'Automatic: %s', 'seo-assistant-for-elementor' ),
				'measured'    => __( 'Sizes were measured in the editor preview.', 'seo-assistant-for-elementor' ),
				'estimated'   => __( 'Sizes are estimated from your Elementor settings.', 'seo-assistant-for-elementor' ),
				'measureDevice' => __( 'Sizes are estimated. Measured sizes are used in the desktop view.', 'seo-assistant-for-elementor' ),
				/* translators: %s: device name (Desktop, Tablet or Mobile) */
				'limitsFor'     => __( 'Limits used: %s', 'seo-assistant-for-elementor' ),
				'desktop'       => __( 'Desktop', 'seo-assistant-for-elementor' ),
				'tablet'        => __( 'Tablet', 'seo-assistant-for-elementor' ),
				'mobile'        => __( 'Mobile', 'seo-assistant-for-elementor' ),
				'measureNarrow' => __( 'Sizes are estimated because the editor window is much narrower than the desktop width set in the plugin settings. Close the side panels or widen the window.', 'seo-assistant-for-elementor' ),
			),
		);
	}
}
