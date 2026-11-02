<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Images: pixel size against the size they are shown at, file weight, format and alt text.
 */
final class NZSA_Rule_Images extends NZSA_Rule {

	public function id(): string {
		return 'images';
	}

	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		$refs = NZSA_Image_Refs::of( $node );
		if ( ! $refs ) {
			return array();
		}

		$viewport = (int) $ctx->setting( 'viewport' );
		$retina   = (float) $ctx->setting( 'retina_factor' );
		$warn_kb  = (int) $ctx->setting( 'image_warn_kb' );
		$error_kb = (int) $ctx->setting( 'image_error_kb' );

		$too_big  = array();
		$too_small = array();
		$heavy    = array();
		$legacy   = array();
		$no_alt   = array();
		$bad_alt  = array();
		$unknown  = 0;
		$no_mobile_bg = array();
		$worst_over   = 0;

		foreach ( $refs as $ref ) {
			$info = $ctx->images->info( $ref['source'], (string) $ref['size'], $ref['custom'] );
			if ( null === $info ) {
				++$unknown;
				continue;
			}

			$display = NZSA_Image_Refs::display_width( $node, $ref, $viewport );
			$needed  = $display * $retina;
			$w       = (int) $info['width'];

			if ( $w > 0 ) {
				if ( $w > $needed * 1.5 ) {
					$too_big[] = array( 'w' => $w, 'display' => $display, 'needed' => $needed, 'info' => $info );
					$worst_over = max( $worst_over, $w / max( 1.0, $needed ) );
				} elseif ( $w < $display ) {
					$too_small[] = array( 'w' => $w, 'display' => $display, 'ratio' => $w / max( 1.0, $display ) );
				}
			}

			$kb = (int) round( (int) $info['bytes'] / 1024 );
			if ( $kb > $warn_kb ) {
				$heavy[] = array( 'kb' => $kb, 'info' => $info );
			}
			if ( $kb > 100 && in_array( $info['mime'], array( 'image/jpeg', 'image/png' ), true ) ) {
				$legacy[] = $info;
			}

			if ( 'background' === $ref['kind'] ) {
				if ( $kb > 150 && ! $ref['mobile_bg'] ) {
					$no_mobile_bg[] = $kb;
				}
			} elseif ( '' === $info['alt'] ) {
				$no_alt[] = $info;
			} elseif ( self::looks_like_filename( (string) $info['alt'] ) ) {
				$bad_alt[] = $info;
			}
		}

		$issues = array();
		$total  = count( $refs );

		if ( $too_big ) {
			$first   = $too_big[0];
			$rec_w   = (int) ( ceil( $first['needed'] / 10 ) * 10 );
			$severe  = $worst_over > 3;
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Dimensions,
				$severe ? NZSA_Severity::Error : NZSA_Severity::Warning,
				'oversize',
				$this->count_prefix( count( $too_big ), $total ) . sprintf(
					/* translators: 1: image width in px, 2: width it is shown at in px */
					__( 'is %1$d px wide but is shown at only about %2$d px.', 'nazari-seo-assistant' ),
					$first['w'],
					(int) round( $first['display'] )
				),
				sprintf(
					/* translators: %d: recommended image width in px */
					__( 'About %d px wide is enough (twice the display width keeps it sharp on high-density screens). Resize the file, or choose a smaller “Image Resolution” in the widget.', 'nazari-seo-assistant' ),
					$rec_w
				),
				array( 'width' => $first['w'], 'display' => (int) round( $first['display'] ), 'recommended' => $rec_w )
			);
		}

		if ( $too_small ) {
			$first    = $too_small[0];
			$rec_w    = (int) ( ceil( $first['display'] * $retina / 10 ) * 10 );
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Dimensions,
				$first['ratio'] < 0.7 ? NZSA_Severity::Error : NZSA_Severity::Warning,
				'undersize',
				$this->count_prefix( count( $too_small ), $total ) . sprintf(
					/* translators: 1: image width in px, 2: width it is shown at in px */
					__( 'is only %1$d px wide but is stretched to about %2$d px, so it will look blurry.', 'nazari-seo-assistant' ),
					$first['w'],
					(int) round( $first['display'] )
				),
				sprintf(
					/* translators: %d: recommended image width in px */
					__( 'Upload a larger version, about %d px wide, or make the image smaller on the page.', 'nazari-seo-assistant' ),
					$rec_w
				),
				array( 'width' => $first['w'], 'display' => (int) round( $first['display'] ), 'recommended' => $rec_w )
			);
		}

		if ( $heavy ) {
			usort( $heavy, static fn( array $a, array $b ): int => $b['kb'] <=> $a['kb'] );
			$top      = $heavy[0]['kb'];
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				$top > $error_kb ? NZSA_Severity::Error : NZSA_Severity::Warning,
				'weight',
				$this->count_prefix( count( $heavy ), $total ) . sprintf(
					/* translators: 1: file size in KB, 2: recommended maximum in KB */
					__( 'weighs %1$d KB (aim for under %2$d KB).', 'nazari-seo-assistant' ),
					$top,
					$warn_kb
				),
				__( 'Compress the image (for example with Squoosh or an optimization plugin) and save it as WebP.', 'nazari-seo-assistant' ),
				array( 'kb' => $top, 'limit' => $warn_kb )
			);
		}

		if ( $legacy && ! $heavy ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Info,
				'format',
				$this->count_prefix( count( $legacy ), $total ) . __( 'is a JPEG/PNG file. WebP or AVIF is usually 25–35% smaller with the same quality.', 'nazari-seo-assistant' ),
				__( 'Convert it to WebP before uploading, or let an image optimization plugin do it.', 'nazari-seo-assistant' )
			);
		}

		if ( $no_mobile_bg ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Warning,
				'mobile_bg',
				sprintf(
					/* translators: %d: file size in KB */
					__( 'The background image (%d KB) is also downloaded by phones.', 'nazari-seo-assistant' ),
					max( $no_mobile_bg )
				),
				__( 'Switch the editor to the mobile view, open Style → Background and upload a smaller image (about 800 px wide) for phones.', 'nazari-seo-assistant' )
			);
		}

		if ( $no_alt ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Access,
				NZSA_Severity::Error,
				'alt_missing',
				$this->count_prefix( count( $no_alt ), $total ) . __( 'has no alternative text (alt).', 'nazari-seo-assistant' ),
				__( 'Open the image in the Media Library and fill in “Alternative Text”: describe the picture in a few words. Search engines and screen readers rely on it.', 'nazari-seo-assistant' )
			);
		}

		if ( $bad_alt ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Access,
				NZSA_Severity::Warning,
				'alt_filename',
				$this->count_prefix( count( $bad_alt ), $total ) . __( 'has an alt text that looks like a file name.', 'nazari-seo-assistant' ),
				__( 'Replace it with a short description of what the picture shows.', 'nazari-seo-assistant' )
			);
		}

		if ( $unknown ) {
			$issues[] = $this->issue(
				$node,
				NZSA_Category::Speed,
				NZSA_Severity::Info,
				'external',
				$this->count_prefix( $unknown, $total ) . __( 'is not in the Media Library, so its size and alt text could not be checked.', 'nazari-seo-assistant' ),
				__( 'Upload the image to the Media Library and select it again.', 'nazari-seo-assistant' )
			);
		}

		return $issues;
	}

	/** “Image” or “2 of 5 images”, ready to be followed by a verb phrase. */
	private function count_prefix( int $count, int $total ): string {
		if ( $total <= 1 ) {
			return __( 'The image', 'nazari-seo-assistant' ) . ' ';
		}
		return sprintf(
			/* translators: 1: number of affected images, 2: total images in the element */
			__( '%1$d of %2$d images', 'nazari-seo-assistant' ),
			$count,
			$total
		) . ' ';
	}

	private static function looks_like_filename( string $alt ): bool {
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', $alt )
			|| (bool) preg_match( '/^(img|dsc|image|screenshot|photo|pic|untitled)[-_ ]?\d*$/i', $alt );
	}
}
