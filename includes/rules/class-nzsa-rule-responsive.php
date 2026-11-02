<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Mobile fit: fixed sizes that were never adjusted for phones.
 */
final class NZSA_Rule_Responsive extends NZSA_Rule {

	private const PHONE_WIDTH = 375;

	public function id(): string {
		return 'responsive';
	}

	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		$issues = array();

		// 1. A fixed pixel width wider than a phone, with no mobile value.
		$width = $node->size( 'width' );
		if ( null !== $width && 'px' === $node->unit( 'width' ) && $width > self::PHONE_WIDTH + 25 && ! $node->has_device( 'width', 'mobile' ) ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Dimensions,
				NZSA_Severity::Warning,
				'width',
				sprintf(
					/* translators: %d: width in px */
					__( 'It has a fixed width of %d px, wider than a phone screen (about 375 px), so it can stick out of the page on mobile.', 'nazari-seo-assistant' ),
					(int) $width
				),
				__( 'Switch the editor to the mobile view and set the width to 100% (or a smaller value).', 'nazari-seo-assistant' ),
				array( 'width' => (int) $width )
			);
		}

		// 2. Very tall blocks.
		$height = $node->size( 'min_height' );
		if ( null !== $height && 'px' === $node->unit( 'min_height' ) && $height >= 500 && ! $node->has_device( 'min_height', 'mobile' ) ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Dimensions,
				NZSA_Severity::Warning,
				'height',
				sprintf(
					/* translators: %d: height in px */
					__( 'The minimum height is %d px on every device, which fills more than a whole phone screen.', 'nazari-seo-assistant' ),
					(int) $height
				),
				__( 'In the mobile view set a smaller minimum height (for example 300 px) or switch it to “min-height: auto”.', 'nazari-seo-assistant' ),
				array( 'height' => (int) $height )
			);
		}

		// 3. Big paddings.
		$padding = $node->get( 'padding' );
		if ( is_array( $padding ) && ! $node->has_device( 'padding', 'mobile' ) && 'px' === ( $padding['unit'] ?? 'px' ) ) {
			$max = 0.0;
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $padding[ $side ] ) && is_numeric( $padding[ $side ] ) ) {
					$max = max( $max, (float) $padding[ $side ] );
				}
			}
			if ( $max > 80 ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Dimensions,
					NZSA_Severity::Info,
					'padding',
					sprintf(
						/* translators: %d: padding in px */
						__( 'The padding is %d px on all devices, which leaves little room for content on a phone.', 'nazari-seo-assistant' ),
						(int) $max
					),
					__( 'In the mobile view reduce the padding to about 20–40 px.', 'nazari-seo-assistant' )
				);
			}
		}

		// 4. Large text without a mobile size.
		if ( in_array( $node->widget, array( 'heading', 'theme-post-title', 'text-editor', 'button' ), true ) ) {
			$font = $node->size( 'typography_font_size' );
			if ( null !== $font && 'px' === $node->unit( 'typography_font_size' ) && $font > 40 && ! $node->has_device( 'typography_font_size', 'mobile' ) ) {
				$issues[] = $this->issue(
					$node,
					NZSA_Category::Dimensions,
					NZSA_Severity::Warning,
					'font',
					sprintf(
						/* translators: %d: font size in px */
						__( 'The text size is %d px on every device. Long words can overflow a phone screen.', 'nazari-seo-assistant' ),
						(int) $font
					),
					__( 'In the mobile view set a smaller font size (about 60% of the desktop size).', 'nazari-seo-assistant' )
				);
			}
		}

		// 5. Rows that never stack.
		if ( 'container' === $node->type && in_array( $node->get( 'flex_direction' ), array( 'row', 'row-reverse' ), true ) && count( $node->children ) >= 3 && 'wrap' !== $node->get( 'flex_wrap' ) && ! $node->has_device( 'flex_direction', 'mobile' ) ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Dimensions,
				NZSA_Severity::Warning,
				'row',
				sprintf(
					/* translators: %d: number of items in the row */
					__( 'The %d items stay in one row on phones, so they get squeezed or overflow.', 'nazari-seo-assistant' ),
					count( $node->children )
				),
				__( 'In the mobile view set the container direction to “Column” (or turn on “Wrap”).', 'nazari-seo-assistant' )
			);
		}

		return $issues;
	}
}
