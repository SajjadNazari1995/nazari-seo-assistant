<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Links, buttons and images inside text: clear wording and nothing empty.
 */
final class NZSA_Rule_Links extends NZSA_Rule {

	private const VAGUE = array(
		'click here', 'here', 'read more', 'more', 'learn more', 'click', 'details',
		'اینجا', 'کلیک کنید', 'بیشتر', 'ادامه مطلب', 'بیشتر بخوانید', 'مشاهده', 'جزئیات',
	);

	public function id(): string {
		return 'links';
	}

	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		if ( ! $node->is_widget() ) {
			return array();
		}

		$issues = array();

		if ( 'button' === $node->widget ) {
			$text = NZSA_Context::plain( (string) $node->get( 'text' ) );
			$url  = is_array( $node->get( 'link' ) ) ? (string) ( $node->get( 'link' )['url'] ?? '' ) : '';

			if ( '' === $text && '' === (string) ( is_array( $node->get( 'selected_icon' ) ) ? ( $node->get( 'selected_icon' )['value'] ?? '' ) : '' ) ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Access,
					NZSA_Severity::Error,
					'button_empty',
					__( 'The button has no text.', 'nazari-seo-assistant' ),
					__( 'Write what the button does, for example “Get a free quote”.', 'nazari-seo-assistant' )
				);
			} elseif ( $this->is_vague( $text ) ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Access,
					NZSA_Severity::Warning,
					'vague',
					sprintf(
						/* translators: %s: button text */
						__( 'The button text “%s” does not say where it leads.', 'nazari-seo-assistant' ),
						NZSA_Context::excerpt( $text )
					),
					__( 'Use words that describe the destination, such as “See our pricing”.', 'nazari-seo-assistant' )
				);
			}

			if ( '' === $url ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Access,
					NZSA_Severity::Warning,
					'button_no_link',
					__( 'The button has no link.', 'nazari-seo-assistant' ),
					__( 'Add a URL in the “Link” field, or remove the button.', 'nazari-seo-assistant' )
				);
			}
		}

		if ( 'text-editor' === $node->widget ) {
			$html = (string) $node->get( 'editor' );

			if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $html, $links ) ) {
				foreach ( $links[1] as $inner ) {
					$text = NZSA_Context::plain( $inner );
					if ( '' !== $text && $this->is_vague( $text ) ) {
						$issues[] = $this->issue(
							$node,
							NZSA_Category::Access,
							NZSA_Severity::Warning,
							'vague_text',
							sprintf(
								/* translators: %s: link text */
								__( 'The link text “%s” does not say where it leads.', 'nazari-seo-assistant' ),
								NZSA_Context::excerpt( $text )
							),
							__( 'Make the link text describe the destination.', 'nazari-seo-assistant' )
						);
						break;
					}
				}
			}

			if ( preg_match_all( '/<img\b[^>]*>/i', $html, $images ) ) {
				$missing = 0;
				foreach ( $images[0] as $tag ) {
					if ( ! preg_match( '/\balt\s*=\s*(["\'])(.+?)\1/i', $tag ) ) {
						++$missing;
					}
				}
				if ( $missing ) {
					$issues[] = $this->issue(
						$node,
						NZSA_Category::Access,
						NZSA_Severity::Error,
						'text_img_alt',
						sprintf(
							/* translators: %d: number of images */
							__( '%d image(s) inside this text have no alt text.', 'nazari-seo-assistant' ),
							$missing
						),
						__( 'In the text editor click the image and fill in “Alternative text”.', 'nazari-seo-assistant' )
					);
				}
			}
		}

		return $issues;
	}

	private function is_vague( string $text ): bool {
		return in_array( mb_strtolower( trim( $text, " .!:→»…" ) ), self::VAGUE, true );
	}
}
