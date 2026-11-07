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

		// Popups are free-standing boxes: any wrapper tag is fine.
		if ( 0 === $node->depth && in_array( $node->type, array( 'section', 'container' ), true ) && 'popup' !== $ctx->role ) {
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
			? __( 'Open the section settings → Layout → HTML Tag', 'seo-assistant-for-elementor' )
			: __( 'Open the container settings → Layout → HTML Tag', 'seo-assistant-for-elementor' );

		if ( 'header' === $role || 'footer' === $role ) {
			$fix = sprintf(
				/* translators: 1: path in the Elementor panel, 2: HTML tag name */
				__( '%1$s and choose “%2$s”.', 'seo-assistant-for-elementor' ),
				$where,
				$role
			);

			if ( $sure ) {
				$by_role = $ctx->role === $role;
				return array(
					$this->issue(
						$node,
						NZSA_Category::Tags,
						NZSA_Severity::Warning,
						$role,
						sprintf(
							$by_role
								/* translators: 1: current HTML tag, 2: expected HTML tag (header or footer) */
								? __( 'This is your site %2$s template, but its tag is “%1$s”.', 'seo-assistant-for-elementor' )
								/* translators: 1: current HTML tag, 2: expected HTML tag */
								: __( 'This looks like your site %2$s, but its tag is “%1$s”.', 'seo-assistant-for-elementor' ),
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
						__( 'If this is the %s of your site, give it that tag so search engines can tell.', 'seo-assistant-for-elementor' ),
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
					__( 'This block is a plain “div”, which tells search engines nothing about it.', 'seo-assistant-for-elementor' ),
					sprintf(
						/* translators: %s: path in the Elementor panel */
						__( '%s and choose “section” (or “article” / “aside” if that fits better).', 'seo-assistant-for-elementor' ),
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
		// A header or footer template is that role for certain, wherever the block sits.
		if ( 'header' === $ctx->role || 'footer' === $ctx->role ) {
			return array( $ctx->role, true );
		}

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
		// When the site has its own header / footer template, the first / last block of a page is not it.
		if ( 0 === $index && $total > 1 && ! NZSA_Roles::site_has_template( 'header' ) ) {
			return array( 'header', false );
		}
		if ( $total > 1 && $index === $total - 1 && ! NZSA_Roles::site_has_template( 'footer' ) ) {
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
		$fragment = $ctx->is_fragment();

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
						__( 'There is an empty H%d heading.', 'seo-assistant-for-elementor' ),
						$level
					),
					__( 'Write the heading text, or delete the widget if it is only used for spacing.', 'seo-assistant-for-elementor' )
				);
				continue;
			}

			if ( 1 === $level ) {
				++$h1_seen;
				if ( $fragment && 1 === $h1_seen ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Tags,
						NZSA_Severity::Warning,
						'h1_template',
						sprintf(
							/* translators: %s: template kind, for example "footer template" */
							__( 'A %s should not contain an H1. It repeats on many pages, and each page needs its own single H1.', 'seo-assistant-for-elementor' ),
							mb_strtolower( NZSA_Roles::label( $ctx->role ) )
						),
						__( 'Set the heading’s “HTML Tag” to div, p or H2–H6 and make it bigger in the Style tab if you need to.', 'seo-assistant-for-elementor' )
					);
				}
				if ( $h1_seen > 1 && ! $fragment ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Tags,
						NZSA_Severity::Error,
						'h1_many',
						sprintf(
							/* translators: %s: heading text */
							__( 'A second H1 (“%s”). A page should have only one.', 'seo-assistant-for-elementor' ),
							NZSA_Context::excerpt( $text )
						),
						__( 'In the Heading widget set “HTML Tag” to H2 (use the Style tab if you only want it bigger).', 'seo-assistant-for-elementor' )
					);
				}
			}

			if ( $previous > 0 && $level > $previous + 1 && ! $fragment ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Tags,
					NZSA_Severity::Warning,
					'skip',
					sprintf(
						/* translators: 1: previous heading level, 2: this heading level */
						__( 'The outline jumps from H%1$d to H%2$d.', 'seo-assistant-for-elementor' ),
						$previous,
						$level
					),
					sprintf(
						/* translators: %d: suggested heading level */
						__( 'Use H%d here, and style it bigger or smaller in the Style tab.', 'seo-assistant-for-elementor' ),
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
						__( 'This heading is %d characters long. Short headings (under about 70) read and rank better.', 'seo-assistant-for-elementor' ),
						mb_strlen( $text )
					),
					__( 'Move the extra words into a paragraph under the heading.', 'seo-assistant-for-elementor' )
				);
			}

			$previous = $level;
		}

		// Whole page: there must be exactly one H1.
		if ( 'page' === $ctx->scope && 0 === $h1_seen && $ctx->roots && ! $fragment ) {
			$issues[] = $this->issue(
				$ctx->roots[0],
				NZSA_Category::Tags,
				NZSA_Severity::Error,
				'h1_missing',
				__( 'The page has no H1 heading.', 'seo-assistant-for-elementor' ),
				__( 'Add one Heading widget with “HTML Tag” = H1, usually in the first big section, describing what the page is about.', 'seo-assistant-for-elementor' )
			);
		}

		// A header block with an H1 is usually the logo text, not the page title.
		if ( $h1_seen >= 1 && ! $fragment ) {
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
						__( 'The H1 is inside the site header. The H1 should be the title of the page, not the logo text.', 'seo-assistant-for-elementor' ),
						__( 'Change this heading to a normal tag and put the H1 in the first content section.', 'seo-assistant-for-elementor' )
					);
					break;
				}
			}
		}

		return $issues;
	}
}
