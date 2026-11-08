<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Sizes measured by the editor in the live preview (getBoundingClientRect), as opposed to the
 * estimates made from Elementor settings. The data comes from the browser, so it is validated
 * and only ever used as numbers.
 */
final class NZSA_Measure {

	public const MAX_ENTRIES = 3000;

	/** Devices where the preview shows the desktop layout. */
	private const DESKTOP_DEVICES = array( 'desktop', 'laptop', 'widescreen' );

	/**
	 * @param array<string,float> $widths Element id => drawn width in CSS px.
	 * @param array<string,float> $images Element id => drawn width of its first image in CSS px.
	 */
	private function __construct(
		public readonly string $device,
		public readonly int $viewport,
		public readonly array $widths,
		public readonly array $images
	) {}

	/**
	 * Device mode named in the request ("desktop" when missing), even when nothing could be measured.
	 *
	 * @param mixed $raw The "measure" object from the request.
	 */
	public static function device_of( mixed $raw ): string {
		$device = is_array( $raw ) && isset( $raw['device'] ) && is_string( $raw['device'] ) ? sanitize_key( $raw['device'] ) : 'desktop';
		return '' !== $device ? substr( $device, 0, 20 ) : 'desktop';
	}

	/**
	 * @param mixed $raw The "measure" object from the request.
	 */
	public static function from( mixed $raw ): ?self {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$device   = self::device_of( $raw );
		$viewport = isset( $raw['viewport'] ) && is_numeric( $raw['viewport'] ) ? (int) $raw['viewport'] : 0;
		$viewport = max( 0, min( 10000, $viewport ) );

		$widths = self::numbers( $raw['widths'] ?? null );
		$images = self::numbers( $raw['images'] ?? null );

		if ( ! $widths && ! $images ) {
			return null;
		}

		return new self( $device, $viewport, $widths, $images );
	}

	/**
	 * @return array<string,float>
	 */
	private static function numbers( mixed $map ): array {
		$out = array();
		if ( ! is_array( $map ) ) {
			return $out;
		}

		foreach ( $map as $key => $value ) {
			if ( count( $out ) >= self::MAX_ENTRIES ) {
				break;
			}
			if ( ! is_numeric( $value ) ) {
				continue;
			}
			$width = (float) $value;
			if ( $width <= 0 || $width > 10000 ) {
				continue;
			}
			$id = NZSA_Tree::clean_id( (string) $key );
			if ( '' !== $id ) {
				$out[ $id ] = round( $width, 1 );
			}
		}

		return $out;
	}

	/** True when the preview was a phone or tablet view. */
	public function is_small_device(): bool {
		return ! in_array( $this->device, self::DESKTOP_DEVICES, true );
	}

	/**
	 * Measured sizes replace the estimates only in a desktop view that is about as wide as the
	 * "Desktop width" setting. A narrow editor window would otherwise squeeze every element.
	 *
	 * @return string "" when usable, otherwise the reason: "device" or "viewport".
	 */
	public function sizing_blocker( int $desktop_width ): string {
		if ( $this->is_small_device() ) {
			return 'device';
		}
		if ( $this->viewport <= 0 || abs( $this->viewport - $desktop_width ) > $desktop_width * 0.15 ) {
			return 'viewport';
		}
		return '';
	}

	/**
	 * Stores the measurements on the nodes.
	 *
	 * @param NZSA_Node[] $roots
	 * @param bool        $use_for_sizing Replace the estimated widths with the measured ones.
	 */
	public function apply( array $roots, bool $use_for_sizing ): void {
		foreach ( $roots as $root ) {
			$this->apply_node( $root, $use_for_sizing );
		}
	}

	private function apply_node( NZSA_Node $node, bool $use_for_sizing ): void {
		if ( isset( $this->widths[ $node->id ] ) ) {
			$node->measured = $this->widths[ $node->id ];
			if ( $use_for_sizing ) {
				$node->width = $node->measured;
			}
		}
		if ( $use_for_sizing && isset( $this->images[ $node->id ] ) ) {
			$node->img_width = $this->images[ $node->id ];
		}
		foreach ( $node->children as $child ) {
			$this->apply_node( $child, $use_for_sizing );
		}
	}
}
