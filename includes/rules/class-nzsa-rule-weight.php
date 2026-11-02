<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Loading speed of a whole block: total image weight, sliders, video, animations, nesting.
 */
final class NZSA_Rule_Weight extends NZSA_Rule {

	public function id(): string {
		return 'weight';
	}

	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		$issues = array();

		// Per widget checks.
		if ( $node->is_widget() ) {
			array_push( $issues, ...$this->widget( $node ) );
		}

		// Block-level checks run once per top-level element (or the clicked element).
		if ( 0 === $node->depth || $this->is_scope_root( $ctx, $node ) ) {
			array_push( $issues, ...$this->block( $ctx, $node ) );
		}

		return $issues;
	}

	private function is_scope_root( NZSA_Context $ctx, NZSA_Node $node ): bool {
		return in_array( $node, $ctx->roots, true );
	}

	/**
	 * @return NZSA_Issue[]
	 */
	private function widget( NZSA_Node $node ): array {
		$issues = array();

		if ( 'image-carousel' === $node->widget ) {
			$slides = is_array( $node->get( 'carousel' ) ) ? count( $node->get( 'carousel' ) ) : 0;
			if ( $slides > 6 ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Speed,
					NZSA_Severity::Warning,
					'slides',
					sprintf(
						/* translators: %d: number of slides */
						__( 'The slider holds %d images, and every one is downloaded even if nobody scrolls to it.', 'nazari-seo-assistant' ),
						$slides
					),
					__( 'Keep it to 3–5 slides, or link to a gallery page for the rest.', 'nazari-seo-assistant' )
				);
			}
		}

		if ( 'video' === $node->widget && in_array( $node->get( 'video_type' ), array( 'youtube', 'vimeo', 'dailymotion' ), true ) && 'yes' !== $node->get( 'lazy_load' ) ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'video_lazy',
				__( 'The video player loads with the page, which adds several hundred KB of scripts.', 'nazari-seo-assistant' ),
				__( 'In the Video widget turn on “Lazy Load” so it loads only when the visitor presses play.', 'nazari-seo-assistant' )
			);
		}

		if ( 'google_maps' === $node->widget ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Info,
				'map',
				__( 'An embedded map is heavy. Keep it low on the page so it does not delay the first screen.', 'nazari-seo-assistant' ),
				__( 'Place it in a section further down, or replace it with a static image that links to the map.', 'nazari-seo-assistant' )
			);
		}

		if ( 'html' === $node->widget && preg_match( '/<iframe\b(?![^>]*\bloading\s*=\s*["\']?lazy)/i', (string) $node->get( 'html' ) ) ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Info,
				'iframe',
				__( 'An iframe in this HTML loads immediately.', 'nazari-seo-assistant' ),
				__( 'Add loading="lazy" to the iframe tag.', 'nazari-seo-assistant' )
			);
		}

		return $issues;
	}

	/**
	 * @return NZSA_Issue[]
	 */
	private function block( NZSA_Context $ctx, NZSA_Node $root ): array {
		$issues = array();
		$nodes  = array_merge( array( $root ), $root->descendants() );

		// Total image weight (each file counted once).
		$seen  = array();
		$bytes = 0;
		foreach ( $nodes as $node ) {
			foreach ( NZSA_Image_Refs::of( $node ) as $ref ) {
				$info = $ctx->images->info( $ref['source'], (string) $ref['size'], $ref['custom'] );
				if ( $info ) {
					$key = $info['id'] . '|' . $ref['size'];
					if ( ! isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$bytes       += (int) $info['bytes'];
					}
				}
			}
		}
		$kb = (int) round( $bytes / 1024 );
		if ( $kb > 1000 ) {
			$issues[] = $this->issue(
				$root,
				NZSA_Category::Speed,
				NZSA_Severity::Error,
				'total',
				sprintf(
					/* translators: %s: size such as 1.4 MB */
					__( 'The images in this block add up to about %s.', 'nazari-seo-assistant' ),
					size_format( $bytes, 1 )
				),
				__( 'Aim for under 500 KB per block: compress the largest images first.', 'nazari-seo-assistant' ),
				array( 'kb' => $kb )
			);
		} elseif ( $kb > 500 ) {
			$issues[] = $this->issue(
				$root,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'total',
				sprintf(
					/* translators: %s: size such as 700 KB */
					__( 'The images in this block add up to about %s.', 'nazari-seo-assistant' ),
					size_format( $bytes, 1 )
				),
				__( 'Aim for under 500 KB per block: compress the largest images first.', 'nazari-seo-assistant' ),
				array( 'kb' => $kb )
			);
		}

		// Background video.
		foreach ( $nodes as $node ) {
			if ( 'video' === $node->get( 'background_background' ) && '' !== (string) $node->get( 'background_video_link' ) ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Speed,
					NZSA_Severity::Warning,
					'bg_video',
					__( 'A background video downloads on every visit, even on phones.', 'nazari-seo-assistant' ),
					__( 'Use a compressed poster image instead, or keep the video under 2 MB and add a mobile fallback image.', 'nazari-seo-assistant' )
				);
			}
		}

		// Animations.
		$animated = 0;
		foreach ( $nodes as $node ) {
			if ( '' !== (string) $node->get( '_animation' ) || '' !== (string) $node->get( 'animation' ) || 'yes' === $node->get( 'motion_fx_motion_fx_scrolling' ) || 'yes' === $node->get( 'motion_fx_motion_fx_mouse' ) ) {
				++$animated;
			}
		}
		if ( $animated > 6 ) {
			$issues[] = $this->issue(
				$root,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'animations',
				sprintf(
					/* translators: %d: number of animated elements */
					__( '%d elements here are animated. Many animations delay interaction and can hurt the “Cumulative Layout Shift” score.', 'nazari-seo-assistant' ),
					$animated
				),
				__( 'Keep animations for the one or two things that matter.', 'nazari-seo-assistant' )
			);
		}

		// Nesting depth and size.
		$max_depth = 0;
		$widgets   = 0;
		foreach ( $nodes as $node ) {
			$max_depth = max( $max_depth, $node->depth - $root->depth );
			if ( $node->is_widget() ) {
				++$widgets;
			}
		}
		$limit = (int) $ctx->setting( 'max_dom_depth' );
		if ( $max_depth + 1 > $limit ) {
			$issues[] = $this->issue(
				$root,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'depth',
				sprintf(
					/* translators: 1: nesting levels, 2: recommended maximum */
					__( 'Containers are nested %1$d levels deep (aim for %2$d or fewer). Deep nesting makes the page heavier to build and style.', 'nazari-seo-assistant' ),
					$max_depth + 1,
					$limit
				),
				__( 'Remove containers that only wrap a single element, or use the parent’s padding and gap instead.', 'nazari-seo-assistant' )
			);
		}
		if ( $widgets > 60 ) {
			$issues[] = $this->issue(
				$root,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'widgets',
				sprintf(
					/* translators: %d: number of widgets */
					__( 'This block has %d widgets, which makes the page heavy.', 'nazari-seo-assistant' ),
					$widgets
				),
				__( 'Split the content into several pages, or use a Loop Grid instead of copying widgets.', 'nazari-seo-assistant' )
			);
		}

		return $issues;
	}
}
