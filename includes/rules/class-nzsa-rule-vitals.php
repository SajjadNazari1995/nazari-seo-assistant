<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Core Web Vitals hints that can be judged from the page data:
 * LCP (the first big image on the page) and CLS (things that load without a reserved size).
 */
final class NZSA_Rule_Vitals extends NZSA_Rule {

	/** Pages where the first image is a likely LCP element. Header, footer and popup templates are not judged. */
	private const LCP_ROLES = array( 'home', 'page', 'single', 'archive' );

	public function id(): string {
		return 'vitals';
	}

	/**
	 * CLS: embedded frames and images written as raw HTML without a size.
	 */
	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		if ( ! $node->is_widget() || ! in_array( $node->widget, array( 'html', 'text-editor' ), true ) ) {
			return array();
		}

		$html = (string) $node->get( 'html' === $node->widget ? 'html' : 'editor' );
		if ( '' === $html || false === stripos( $html, '<' ) ) {
			return array();
		}

		$issues = array();

		if ( preg_match_all( '/<iframe\b[^>]*>/i', $html, $frames ) ) {
			foreach ( $frames[0] as $tag ) {
				if ( ! self::has_height( $tag ) ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Speed,
						NZSA_Severity::Warning,
						'cls_iframe',
						__( 'An embedded frame (iframe) has no height, so the content below jumps when it loads.', 'seo-assistant-for-elementor' ),
						__( 'Add a height attribute to the iframe (for example height="400"), or give it an aspect-ratio in CSS.', 'seo-assistant-for-elementor' )
					);
					break;
				}
			}
		}

		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $images ) ) {
			foreach ( $images[0] as $tag ) {
				$sized = preg_match( '/\bwidth\s*=/i', $tag ) && preg_match( '/\bheight\s*=/i', $tag );
				if ( ! $sized && ! preg_match( '/aspect-ratio\s*:/i', $tag ) ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Speed,
						NZSA_Severity::Warning,
						'cls_image',
						__( 'An image written in HTML has no width and height, so the content below jumps when it loads.', 'seo-assistant-for-elementor' ),
						__( 'Add width and height attributes to the img tag (the real pixel size of the file). CSS can still make it responsive.', 'seo-assistant-for-elementor' )
					);
					break;
				}
			}
		}

		return $issues;
	}

	/**
	 * LCP: the first image of the page is what visitors usually wait for.
	 */
	public function check_page( NZSA_Context $ctx ): array {
		if ( ! in_array( $ctx->role, self::LCP_ROLES, true ) ) {
			return array();
		}

		foreach ( $ctx->roots as $root ) {
			// Only the first top-level block of the page counts as "above the fold".
			if ( 0 !== $ctx->position_of( $root )[0] ) {
				continue;
			}

			$nodes = array_merge( array( $root ), $root->descendants() );
			foreach ( $nodes as $node ) {
				$refs = NZSA_Image_Refs::of( $node );
				if ( $refs ) {
					return $this->lcp_issues( $ctx, $node, $refs[0] );
				}
			}
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $ref
	 * @return NZSA_Issue[]
	 */
	private function lcp_issues( NZSA_Context $ctx, NZSA_Node $node, array $ref ): array {
		$issues = array();

		$info = $ctx->images->info( $ref['source'], (string) $ref['size'], $ref['custom'] );
		if ( $info ) {
			$kb     = (int) round( (int) $info['bytes'] / 1024 );
			$limit  = (int) $ctx->setting( 'lcp_kb' );
			if ( $kb > $limit ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Speed,
					NZSA_Severity::Warning,
					'lcp_weight',
					sprintf(
						/* translators: 1: file size in KB, 2: recommended maximum in KB */
						__( 'This is the first image on the page, so visitors wait for it: it weighs %1$d KB (aim for under %2$d KB).', 'seo-assistant-for-elementor' ),
						$kb,
						$limit
					),
					__( 'Compress it and save it as WebP. The top image has the biggest effect on how fast the page feels (LCP).', 'seo-assistant-for-elementor' ),
					array(
						'kb'    => $kb,
						'limit' => $limit,
					)
				);
			}
		}

		if ( 'background' === $ref['kind'] ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Info,
				'lcp_background',
				__( 'The first image on the page is a CSS background. Browsers find background images later than normal images, so it appears later.', 'seo-assistant-for-elementor' ),
				__( 'If this is your main visual, use an Image widget instead of a background image.', 'seo-assistant-for-elementor' )
			);
		}

		for ( $n = $node; $n; $n = $n->parent ) {
			$animation = (string) $n->get( $n->is_widget() ? '_animation' : 'animation' );
			if ( '' !== $animation && 'none' !== $animation ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Speed,
					NZSA_Severity::Warning,
					'lcp_animation',
					__( 'The first image on the page has an entrance animation. It stays hidden until the animation starts, which delays the page.', 'seo-assistant-for-elementor' ),
					__( 'Remove the Entrance Animation from this image and from the block around it (Advanced tab → Motion Effects).', 'seo-assistant-for-elementor' )
				);
				break;
			}
		}

		return $issues;
	}

	/** True when an HTML tag states a height (attribute or inline style). */
	private static function has_height( string $tag ): bool {
		return (bool) preg_match( '/\bheight\s*=/i', $tag )
			|| (bool) preg_match( '/style\s*=\s*["\'][^"\']*(?:height|aspect-ratio)\s*:/i', $tag );
	}
}
