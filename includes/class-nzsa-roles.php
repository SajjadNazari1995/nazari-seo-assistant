<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * What the page being reviewed is for: a normal page, the home page, a single post template,
 * an archive, or a header / footer / popup template. The role changes which checks make sense
 * (a footer has no H1, a home page hero should load fast, and so on).
 */
final class NZSA_Roles {

	/** Value that means "work it out from the page". */
	public const AUTO = 'auto';

	/** @var array<string,bool> */
	private static array $template_cache = array();

	/**
	 * @return array<string,string> Role key => label.
	 */
	public static function labels(): array {
		return array(
			'page'    => __( 'Page', 'seo-assistant-for-elementor' ),
			'home'    => __( 'Home page', 'seo-assistant-for-elementor' ),
			'single'  => __( 'Single post or product', 'seo-assistant-for-elementor' ),
			'archive' => __( 'Archive', 'seo-assistant-for-elementor' ),
			'header'  => __( 'Header template', 'seo-assistant-for-elementor' ),
			'footer'  => __( 'Footer template', 'seo-assistant-for-elementor' ),
			'popup'   => __( 'Popup', 'seo-assistant-for-elementor' ),
		);
	}

	public static function label( string $role ): string {
		$labels = self::labels();
		return $labels[ $role ] ?? $labels['page'];
	}

	public static function is_valid( string $role ): bool {
		return isset( self::labels()[ $role ] );
	}

	/** Header, footer and popup templates are fragments: they repeat across pages and have no page title of their own. */
	public static function is_fragment( string $role ): bool {
		return in_array( $role, array( 'header', 'footer', 'popup' ), true );
	}

	/**
	 * The role to use: the one the person picked, or the detected one.
	 */
	public static function resolve( string $requested, int $post_id ): string {
		$requested = sanitize_key( $requested );
		if ( self::AUTO !== $requested && self::is_valid( $requested ) ) {
			return $requested;
		}
		return self::detect( $post_id );
	}

	/**
	 * Works the role out from the document being edited.
	 */
	public static function detect( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return 'page';
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return 'page';
		}

		if ( 'elementor_library' === $post->post_type ) {
			return self::from_template_type( (string) get_post_meta( $post_id, '_elementor_template_type', true ) );
		}

		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
			return 'home';
		}

		return 'page' === $post->post_type ? 'page' : 'single';
	}

	/**
	 * Elementor Pro theme-builder template type → role.
	 */
	public static function from_template_type( string $type ): string {
		$type = sanitize_key( $type );

		if ( in_array( $type, array( 'header', 'footer', 'popup' ), true ) ) {
			return $type;
		}
		if ( in_array( $type, array( 'archive', 'search-results', 'product-archive', 'loop-item' ), true ) ) {
			return 'archive';
		}
		if ( 0 === strpos( $type, 'single' ) || 'product' === $type ) {
			return 'single';
		}

		return 'page';
	}

	/**
	 * True when the site has a published Elementor theme-builder template of this type that is
	 * assigned somewhere (so pages do not need to carry their own header or footer).
	 */
	public static function site_has_template( string $type ): bool {
		$type = sanitize_key( $type );
		if ( isset( self::$template_cache[ $type ] ) ) {
			return self::$template_cache[ $type ];
		}

		$found = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_elementor_template_type',
						'value' => $type,
					),
					array(
						'key'     => '_elementor_conditions',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		self::$template_cache[ $type ] = ! empty( $found );
		return self::$template_cache[ $type ];
	}
}
