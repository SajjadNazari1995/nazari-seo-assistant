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
				),
			)
		);
	}

	public static function can_analyze( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'nzsa_forbidden', __( 'You are not allowed to do this.', 'nazari-seo-assistant' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'nzsa_forbidden', __( 'You are not allowed to edit this page.', 'nazari-seo-assistant' ), array( 'status' => 403 ) );
		}

		if ( strlen( $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'nzsa_too_large', __( 'This block is too large to analyse at once.', 'nazari-seo-assistant' ), array( 'status' => 413 ) );
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
			absint( $request->get_param( 'post_id' ) )
		);

		$response = rest_ensure_response( $report );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
