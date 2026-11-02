<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Finds the images an element uses and how they are displayed.
 */
final class NZSA_Image_Refs {

	/** Widgets that show images, and the settings that hold them. */
	private const IMAGE_WIDGETS = array(
		'image'          => array( 'image', 'image_size', 'image_custom_dimension' ),
		'image-box'      => array( 'image', 'thumbnail_size', 'thumbnail_custom_dimension' ),
		'testimonial'    => array( 'testimonial_image', 'testimonial_image_size', 'testimonial_image_custom_dimension' ),
		'image-hotspot'  => array( 'image', 'image_size', 'image_custom_dimension' ),
	);

	/**
	 * @return array<int,array<string,mixed>> Each: source (id|url), size, custom, kind (content|background), mobile_bg (bool)
	 */
	public static function of( NZSA_Node $node ): array {
		$refs = array();

		if ( $node->is_widget() ) {
			if ( isset( self::IMAGE_WIDGETS[ $node->widget ] ) ) {
				list( $key, $size_key, $custom_key ) = self::IMAGE_WIDGETS[ $node->widget ];
				$source = self::source( $node->get( $key ) );
				if ( null !== $source ) {
					$refs[] = self::ref( $source, (string) $node->get( $size_key, 'large' ), self::custom( $node->get( $custom_key ) ), 'content' );
				}
			}

			if ( 'image-carousel' === $node->widget ) {
				foreach ( self::gallery( $node->get( 'carousel' ) ) as $source ) {
					$refs[] = self::ref( $source, (string) $node->get( 'thumbnail_size', 'medium' ), self::custom( $node->get( 'thumbnail_custom_dimension' ) ), 'content' );
				}
			}

			if ( in_array( $node->widget, array( 'image-gallery', 'gallery' ), true ) ) {
				foreach ( self::gallery( $node->get( 'wp_gallery' ) ) as $source ) {
					$refs[] = self::ref( $source, (string) $node->get( 'thumbnail_size', 'thumbnail' ), self::custom( $node->get( 'thumbnail_custom_dimension' ) ), 'content' );
				}
			}
		}

		// Background images of sections, columns, containers and widgets (Classic type only).
		if ( 'classic' === $node->get( 'background_background' ) ) {
			$source = self::source( $node->get( 'background_image' ) );
			if ( null !== $source ) {
				$mobile = ! empty( $node->settings['background_image_mobile']['url'] ) || ! empty( $node->settings['background_image_mobile']['id'] );
				$refs[] = self::ref( $source, 'full', null, 'background', $mobile );
			}
		}

		return $refs;
	}

	/**
	 * @param int|string $source
	 * @param array{0:int,1:int}|null $custom
	 * @return array<string,mixed>
	 */
	private static function ref( int|string $source, string $size, ?array $custom, string $kind, bool $mobile_bg = false ): array {
		return array(
			'source'    => $source,
			'size'      => '' !== $size ? $size : 'full',
			'custom'    => $custom,
			'kind'      => $kind,
			'mobile_bg' => $mobile_bg,
		);
	}

	/** Media control value → attachment ID, or URL, or null when empty. */
	private static function source( mixed $value ): int|string|null {
		if ( ! is_array( $value ) ) {
			return null;
		}
		if ( ! empty( $value['id'] ) && is_numeric( $value['id'] ) ) {
			return (int) $value['id'];
		}
		if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
			// Elementor's placeholder image is not a real file of the site.
			return false !== strpos( $value['url'], 'placeholder' ) ? null : $value['url'];
		}
		return null;
	}

	/**
	 * @return array<int,int|string>
	 */
	private static function gallery( mixed $items ): array {
		$out = array();
		if ( is_array( $items ) ) {
			foreach ( array_slice( $items, 0, 200 ) as $item ) {
				$source = self::source( $item );
				if ( null !== $source ) {
					$out[] = $source;
				}
			}
		}
		return $out;
	}

	/**
	 * @return array{0:int,1:int}|null
	 */
	private static function custom( mixed $value ): ?array {
		if ( is_array( $value ) && ! empty( $value['width'] ) && ! empty( $value['height'] ) && is_numeric( $value['width'] ) && is_numeric( $value['height'] ) ) {
			return array( (int) $value['width'], (int) $value['height'] );
		}
		return null;
	}

	/**
	 * Width in CSS px the image is drawn at.
	 */
	public static function display_width( NZSA_Node $node, array $ref, int $viewport ): float {
		if ( 'background' === $ref['kind'] ) {
			return 0 === $node->depth ? (float) $viewport : max( 1.0, $node->width );
		}

		$width = max( 1.0, $node->width );
		if ( 'image-box' === $node->widget && in_array( $node->get( 'image_position' ), array( 'left', 'right' ), true ) ) {
			$size = $node->size( 'image_size' );
			return null !== $size && $size > 0 ? $width * $size / 100 : $width * 0.3;
		}
		return $width;
	}
}
