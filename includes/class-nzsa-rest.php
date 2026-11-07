<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint the editor window calls: POST /wp-json/nazari-seo/v1/analyze
 */
final class NZSA_Rest {

	public const NAMESPACE = 'nazari-seo/v1';

	/** Largest request body accepted, in bytes. */
	public const MAX_BODY = 1048576;

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'analyze' ),
				'permission_callback' => array( self::class, 'can_analyze' ),
				'args'                => array(
					'elements' => array(
						'required'          => true,
						'validate_callback' => static fn( $value ): bool => is_array( $value ) && count( $value ) <= 500,
					),
					'scope'    => array(
						'type'    => 'string',
						'enum'    => array( 'element', 'page' ),
						'default' => 'element',
					),
					'index'    => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'total'    => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'post_id'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'role'     => array(
						'type'    => 'string',
						'enum'    => array_merge( array( NZSA_Roles::AUTO ), array_keys( NZSA_Roles::labels() ) ),
						'default' => NZSA_Roles::AUTO,
					),
					'measure'  => array(
						'required'          => false,
						'validate_callback' => static fn( $value ): bool => null === $value || is_array( $value ),
					),
				),
			)
		);
	}

	public static function can_analyze( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'nzsa_forbidden', __( 'You are not allowed to do this.', 'seo-assistant-for-elementor' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'nzsa_forbidden', __( 'You are not allowed to edit this page.', 'seo-assistant-for-elementor' ), array( 'status' => 403 ) );
		}

		if ( strlen( $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'nzsa_too_large', __( 'This block is too large to analyse at once.', 'seo-assistant-for-elementor' ), array( 'status' => 413 ) );
		}

		return true;
	}

	public static function analyze( WP_REST_Request $request ): WP_REST_Response {
		$elements = (array) $request->get_param( 'elements' );

		$report = ( new NZSA_Analyzer() )->analyze(
			array_values( $elements ),
			(string) $request->get_param( 'scope' ),
			(int) $request->get_param( 'index' ),
			(int) $request->get_param( 'total' ),
			absint( $request->get_param( 'post_id' ) ),
			(string) $request->get_param( 'role' ),
			$request->get_param( 'measure' )
		);

		$response = rest_ensure_response( $report );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
