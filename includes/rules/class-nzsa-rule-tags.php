<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * HTML tags: header / footer / section landmarks and the heading outline (one H1, no skipped levels).
 */
final class NZSA_Rule_Tags extends NZSA_Rule {

	private const HEADER_WIDGETS = array( 'nav-menu', 'navigation-menu', 'site-logo', 'theme-site-logo', 'search-form', 'woocommerce-menu-cart' );
	private const FOOTER_WIDGETS = array( 'social-icons', 'share-buttons' );

	public function id(): string {
		return 'tags';
	}

	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		$issues = array();

		if ( 0 === $node->depth && in_array( $node->type, array( 'section', 'container' ), true ) ) {
			array_push( $issues, ...$this->landmark( $ctx, $node ) );
		}

		return $issues;
	}

	/**
	 * @return NZSA_Issue[]
	 */
	private function landmark( NZSA_Context $ctx, NZSA_Node $node ): array {
		$tag = strtolower( (string) $node->get( 'html_tag' ) );
		if ( '' === $tag ) {
			$tag = 'section' === $node->type ? 'section' : 'div';
		}

		list( $role, $sure ) = $this->role( $ctx, $node );
		$expected            = $role;

		if ( $tag === $expected ) {
			return array();
		}

		$where = 'section' === $node->type
			? __( 'Open the section settings → Layout → HTML Tag', 'nazari-seo-assistant' )
			: __( 'Open the container settings → Layout → HTML Tag', 'nazari-seo-assistant' );

		if ( 'header' === $role || 'footer' === $role ) {
			$fix = sprintf(
				/* translators: 1: path in the Elementor panel, 2: HTML tag name */
				__( '%1$s and choose “%2$s”.', 'nazari-seo-assistant' ),
				$where,
				$role
			);

			if ( $sure ) {
				return array(
					$this->issue(
						$node,
						NZSA_Category::Tags,
						NZSA_Severity::Warning,
						$role,
						sprintf(
							/* translators: 1: current HTML tag, 2: expected HTML tag */
							__( 'This looks like your site %2$s, but its tag is “%1$s”.', 'nazari-seo-assistant' ),
							$tag,
							$role
						),
						$fix,
						array( 'tag' => $tag, 'expected' => $role )
					),
				);
			}

			// Only the position suggests the role: a tip, not a fault.
			return 'header' === $tag || 'footer' === $tag ? array() : array(
				$this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Info,
					$role . '_maybe',
					sprintf(
						/* translators: %s: header or footer */
						__( 'If this is the %s of your site, give it that tag so search engines can tell.', 'nazari-seo-assistant' ),
						$role
					),
					$fix,
					array( 'tag' => $tag, 'expected' => $role )
				),
			);
		}

		// A plain content block that is still a bare <div>.
		if ( 'div' === $tag ) {
			return array(
				$this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Warning,
					'generic',
					__( 'This block is a plain “div”, which tells search engines nothing about it.', 'nazari-seo-assistant' ),
					sprintf(
						/* translators: %s: path in the Elementor panel */
						__( '%s and choose “section” (or “article” / “aside” if that fits better).', 'nazari-seo-assistant' ),
						$where
					),
					array( 'tag' => $tag, 'expected' => 'section' )
				),
			);
		}

		return array();
	}

	/**
	 * Guesses what a top-level block is.
	 *
	 * @return array{0:string,1:bool} Role (header, footer or section) and whether the content confirms it.
	 */
	private function role( NZSA_Context $ctx, NZSA_Node $node ): array {
		list( $index, $total ) = $ctx->position_of( $node );
		$widgets               = array();
		$text                  = '';

		foreach ( $node->descendants() as $child ) {
			if ( $child->is_widget() ) {
				$widgets[ $child->widget ] = true;
				if ( 'text-editor' === $child->widget || 'heading' === $child->widget ) {
					$text .= ' ' . NZSA_Context::plain( (string) $child->get( 'editor', $child->get( 'title' ) ) );
				}
			}
		}

		$has_header_cue = (bool) array_intersect( array_keys( $widgets ), self::HEADER_WIDGETS );
		$has_footer_cue = (bool) array_intersect( array_keys( $widgets ), self::FOOTER_WIDGETS )
			|| (bool) preg_match( '/©|copyright|all rights reserved|حقوق|کپی\s*رایت/iu', $text );

		if ( 0 === $index && $has_header_cue ) {
			return array( 'header', true );
		}
		if ( $total > 1 && $index === $total - 1 && $has_footer_cue ) {
			return array( 'footer', true );
		}
		if ( 0 === $index && $total > 1 ) {
			return array( 'header', false );
		}
		if ( $total > 1 && $index === $total - 1 ) {
			return array( 'footer', false );
		}

		return array( 'section', false );
	}

	/**
	 * Heading outline: one H1, no skipped levels, no empty headings.
	 *
	 * @return NZSA_Issue[]
	 */
	public function check_page( NZSA_Context $ctx ): array {
		$issues   = array();
		$headings = $ctx->headings();
		$h1_seen  = 0;
		$previous = 0;

		foreach ( $headings as $heading ) {
			$node  = $heading['node'];
			$level = $heading['level'];
			$text  = $heading['text'];

			if ( '' === $text ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Error,
					'empty',
					sprintf(
						/* translators: %d: heading level number */
						__( 'There is an empty H%d heading.', 'nazari-seo-assistant' ),
						$level
					),
					__( 'Write the heading text, or delete the widget if it is only used for spacing.', 'nazari-seo-assistant' )
				);
				continue;
			}

			if ( 1 === $level ) {
				++$h1_seen;
				if ( $h1_seen > 1 ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Tags,
						NZSA_Severity::Error,
						'h1_many',
						sprintf(
							/* translators: %s: heading text */
							__( 'A second H1 (“%s”). A page should have only one.', 'nazari-seo-assistant' ),
							NZSA_Context::excerpt( $text )
						),
						__( 'In the Heading widget set “HTML Tag” to H2 (use the Style tab if you only want it bigger).', 'nazari-seo-assistant' )
					);
				}
			}

			if ( $previous > 0 && $level > $previous + 1 ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Warning,
					'skip',
					sprintf(
						/* translators: 1: previous heading level, 2: this heading level */
						__( 'The outline jumps from H%1$d to H%2$d.', 'nazari-seo-assistant' ),
						$previous,
						$level
					),
					sprintf(
						/* translators: %d: suggested heading level */
						__( 'Use H%d here, and style it bigger or smaller in the Style tab.', 'nazari-seo-assistant' ),
						$previous + 1
					),
					array( 'from' => $previous, 'to' => $level )
				);
			}

			if ( mb_strlen( $text ) > 70 && 'dynamic' !== $text ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Info,
					'long',
					sprintf(
						/* translators: %d: number of characters */
						__( 'This heading is %d characters long. Short headings (under about 70) read and rank better.', 'nazari-seo-assistant' ),
						mb_strlen( $text )
					),
					__( 'Move the extra words into a paragraph under the heading.', 'nazari-seo-assistant' )
				);
			}

			$previous = $level;
		}

		// Whole page: there must be exactly one H1.
		if ( 'page' === $ctx->scope && 0 === $h1_seen && $ctx->roots ) {
			$issues[] = $this->issue(
				$ctx->roots[0],
				NZSA_Category::Tags,
				NZSA_Severity::Error,
				'h1_missing',
				__( 'The page has no H1 heading.', 'nazari-seo-assistant' ),
				__( 'Add one Heading widget with “HTML Tag” = H1, usually in the first big section, describing what the page is about.', 'nazari-seo-assistant' )
			);
		}

		// A header block with an H1 is usually the logo text, not the page title.
		if ( $h1_seen >= 1 ) {
			foreach ( $headings as $heading ) {
				if ( 1 !== $heading['level'] || '' === $heading['text'] ) {
					continue;
				}
				$root = $heading['node'];
				while ( $root->parent ) {
					$root = $root->parent;
				}
				if ( 0 === $root->depth && in_array( $root->type, array( 'section', 'container' ), true ) && 'header' === $this->role( $ctx, $root )[0] && $this->role( $ctx, $root )[1] ) {
					$issues[] = $this->issue(
						$heading['node'],
						NZSA_Category::Tags,
						NZSA_Severity::Warning,
						'h1_header',
						__( 'The H1 is inside the site header. The H1 should be the title of the page, not the logo text.', 'nazari-seo-assistant' ),
						__( 'Change this heading to a normal tag and put the H1 in the first content section.', 'nazari-seo-assistant' )
					);
					break;
				}
			}
		}

		return $issues;
	}
}
