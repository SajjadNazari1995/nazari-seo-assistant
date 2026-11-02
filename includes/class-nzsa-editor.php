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
	 * @return array<string,mixed>
	 */
	public static function config(): array {
		return array(
			'endpoint' => esc_url_raw( rest_url( NZSA_Rest::NAMESPACE . '/analyze' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'i18n'     => array(
				'menu'        => __( 'Review structure', 'nazari-seo-assistant' ),
				'title'       => __( 'Structure review', 'nazari-seo-assistant' ),
				'recheck'     => __( 'Check again', 'nazari-seo-assistant' ),
				'close'       => __( 'Close', 'nazari-seo-assistant' ),
				'checking'    => __( 'Checking…', 'nazari-seo-assistant' ),
				'ok'          => __( 'No problems found here.', 'nazari-seo-assistant' ),
				'summary'     => __( 'Summary', 'nazari-seo-assistant' ),
				'score'       => __( 'Score', 'nazari-seo-assistant' ),
				'outOf'       => __( 'out of', 'nazari-seo-assistant' ),
				'priorities'  => __( 'Fix these first', 'nazari-seo-assistant' ),
				'nothingTop'  => __( 'Nothing urgent. Great work!', 'nazari-seo-assistant' ),
				'locate'      => __( 'Show in editor', 'nazari-seo-assistant' ),
				'error'       => __( 'The review could not be completed. Please try again.', 'nazari-seo-assistant' ),
				/* translators: 1: number of elements, 2: number of images, 3: image weight in KB */
				'stats'       => __( 'Elements: %1$d · Images: %2$d · Image weight: %3$d KB', 'nazari-seo-assistant' ),
				'fix'         => __( 'What to do', 'nazari-seo-assistant' ),
				'section'     => __( 'Section', 'nazari-seo-assistant' ),
				'container'   => __( 'Container', 'nazari-seo-assistant' ),
				'column'      => __( 'Column', 'nazari-seo-assistant' ),
				'widget'      => __( 'Widget', 'nazari-seo-assistant' ),
				'excellent'   => __( 'Excellent', 'nazari-seo-assistant' ),
				'good'        => __( 'Good', 'nazari-seo-assistant' ),
				'needsWork'   => __( 'Needs work', 'nazari-seo-assistant' ),
				'poor'        => __( 'Poor', 'nazari-seo-assistant' ),
				'scopeElement' => __( 'This element', 'nazari-seo-assistant' ),
				'scopePage'   => __( 'Whole page', 'nazari-seo-assistant' ),
				'showAll'     => __( 'Show elements without problems', 'nazari-seo-assistant' ),
			),
		);
	}
}
