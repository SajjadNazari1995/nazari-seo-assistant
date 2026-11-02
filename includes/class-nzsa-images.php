<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Looks up facts about images in the media library: pixel size, file size, type and alt text.
 * Nothing here touches the network: only attachments that exist on this site are read.
 */
final class NZSA_Images {

	/** @var array<string,array<string,mixed>|null> */
	private array $cache = array();

	/**
	 * @param int|string $id_or_url Attachment ID, or an image URL from this site.
	 * @param string     $size      Registered image size name, "full", or "custom".
	 * @param array{0:int,1:int}|null $custom Width and height when $size is "custom".
	 * @return array<string,mixed>|null null when the image is not in the media library.
	 */
	public function info( int|string $id_or_url, string $size = 'full', ?array $custom = null ): ?array {
		$id = is_numeric( $id_or_url ) ? (int) $id_or_url : 0;
		if ( ! $id && is_string( $id_or_url ) && '' !== $id_or_url ) {
			$id = (int) attachment_url_to_postid( $id_or_url );
		}
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return null;
		}

		$key = $id . '|' . $size . '|' . wp_json_encode( $custom );
		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		return $this->cache[ $key ] = $this->build( $id, $size, $custom );
	}

	/**
	 * @param array{0:int,1:int}|null $custom
	 * @return array<string,mixed>
	 */
	private function build( int $id, string $size, ?array $custom ): array {
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();

		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );
		$file   = (string) ( $meta['file'] ?? '' );
		$bytes  = isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
		$sizes  = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();

		$path = get_attached_file( $id );
		$dir  = $path ? dirname( (string) $path ) : '';

		if ( 'custom' === $size && $custom ) {
			// Elementor crops a new file at this size; its weight is estimated from the original.
			$w = max( 1, $custom[0] );
			$h = max( 1, $custom[1] );
			if ( $width > 0 && $w <= $width ) {
				$bytes = $bytes > 0 ? (int) round( $bytes * min( 1.0, ( $w * $h ) / max( 1, $width * $height ) ) ) : 0;
			}
			$width  = $w;
			$height = $h;
		} elseif ( 'full' !== $size && isset( $sizes[ $size ]['width'] ) ) {
			$width  = (int) $sizes[ $size ]['width'];
			$height = (int) ( $sizes[ $size ]['height'] ?? 0 );
			$bytes  = isset( $sizes[ $size ]['filesize'] ) ? (int) $sizes[ $size ]['filesize'] : 0;
			if ( ! $bytes && $dir && ! empty( $sizes[ $size ]['file'] ) ) {
				$candidate = $dir . '/' . $sizes[ $size ]['file'];
				$bytes     = is_readable( $candidate ) ? (int) filesize( $candidate ) : 0;
			}
		}

		if ( ! $bytes && $path && 'full' === $size && is_readable( (string) $path ) ) {
			$bytes = (int) filesize( (string) $path );
		}

		$mime = (string) get_post_mime_type( $id );
		if ( '' === $file && $path ) {
			$file = basename( (string) $path );
		}

		return array(
			'id'     => $id,
			'width'  => $width,
			'height' => $height,
			'bytes'  => $bytes,
			'mime'   => $mime,
			'alt'    => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			'file'   => basename( $file ),
		);
	}

	/** Modern formats that are already compressed well. */
	public static function is_modern( string $mime ): bool {
		return in_array( $mime, array( 'image/webp', 'image/avif', 'image/svg+xml' ), true );
	}
}
