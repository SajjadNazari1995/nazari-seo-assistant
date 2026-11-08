<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (one option) with defaults and sanitizing.
 *
 * Limits that depend on the screen (image weight, sharpness, screen width) are stored once per
 * device as `{field}_{device}`, for example `image_warn_kb_mobile`.
 */
final class NZSA_Settings {

	public const OPTION = 'nzsa_settings';

	public const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * Settings that have one value per device. Arrays are listed in DEVICES order.
	 *
	 * @return array<string,array{float:bool,default:array<int,int|float>,min:array<int,int|float>,max:array<int,int|float>}>
	 */
	public static function device_fields(): array {
		return array(
			'viewport'       => array(
				'float'   => false,
				'default' => array( 1440, 768, 390 ),
				'min'     => array( 1024, 600, 320 ),
				'max'     => array( 2560, 1366, 600 ),
			),
			'image_warn_kb'  => array(
				'float'   => false,
				'default' => array( 200, 150, 100 ),
				'min'     => array( 20, 20, 20 ),
				'max'     => array( 5000, 5000, 5000 ),
			),
			'image_error_kb' => array(
				'float'   => false,
				'default' => array( 500, 350, 250 ),
				'min'     => array( 20, 20, 20 ),
				'max'     => array( 20000, 20000, 20000 ),
			),
			'lcp_kb'         => array(
				'float'   => false,
				'default' => array( 150, 120, 100 ),
				'min'     => array( 30, 30, 30 ),
				'max'     => array( 2000, 2000, 2000 ),
			),
			'retina_factor'  => array(
				'float'   => true,
				'default' => array( 2.0, 2.0, 2.0 ),
				'min'     => array( 1.0, 1.0, 1.0 ),
				'max'     => array( 3.0, 3.0, 3.0 ),
			),
		);
	}

	/** Names of every field the settings form may send. */
	public static function keys(): array {
		$keys = array( 'max_dom_depth', 'delete_on_uninstall' );
		foreach ( array_keys( self::device_fields() ) as $field ) {
			foreach ( self::DEVICES as $device ) {
				$keys[] = $field . '_' . $device;
			}
			$keys[] = $field; // Value saved by version 1.0 / 1.1, used for desktop.
		}
		return $keys;
	}

	/**
	 * @return array<string,int|float>
	 */
	public static function defaults(): array {
		$out = array(
			'max_dom_depth'       => 6,
			'delete_on_uninstall' => 0,
		);
		foreach ( self::device_fields() as $field => $def ) {
			foreach ( self::DEVICES as $i => $device ) {
				$out[ $field . '_' . $device ] = $def['default'][ $i ];
			}
		}
		return $out;
	}

	/**
	 * @return array<string,int|float>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Maps an Elementor device mode (desktop, laptop, tablet_extra, mobile_extra, ...) to desktop, tablet or mobile.
	 */
	public static function device_for( string $mode ): string {
		if ( str_starts_with( $mode, 'tablet' ) ) {
			return 'tablet';
		}
		if ( str_starts_with( $mode, 'mobile' ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * The settings as the rules read them: device fields without the suffix.
	 *
	 * @param string                   $device One of DEVICES.
	 * @param array<string,int|float>|null $all    Result of all(), loaded when omitted.
	 * @return array<string,int|float>
	 */
	public static function for_device( string $device, ?array $all = null ): array {
		$all    = $all ?? self::all();
		$device = in_array( $device, self::DEVICES, true ) ? $device : 'desktop';

		$out = array(
			'max_dom_depth'       => $all['max_dom_depth'],
			'delete_on_uninstall' => $all['delete_on_uninstall'],
		);
		foreach ( array_keys( self::device_fields() ) as $field ) {
			$out[ $field ] = $all[ $field . '_' . $device ];
		}
		return $out;
	}

	public static function get( string $key ): int|float {
		$all = self::all();
		return $all[ $key ] ?? 0;
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,int|float>
	 */
	public static function sanitize( array $input ): array {
		$out = array(
			'max_dom_depth'       => self::clamp( $input['max_dom_depth'] ?? 6, 3, 15, false ),
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);

		foreach ( self::device_fields() as $field => $def ) {
			foreach ( self::DEVICES as $i => $device ) {
				$key = $field . '_' . $device;
				if ( isset( $input[ $key ] ) ) {
					$value = $input[ $key ];
				} elseif ( 0 === $i && isset( $input[ $field ] ) ) {
					$value = $input[ $field ]; // Setting saved by an older version.
				} else {
					$value = $def['default'][ $i ];
				}
				$out[ $key ] = self::clamp( $value, $def['min'][ $i ], $def['max'][ $i ], $def['float'] );
			}
		}

		// "Fix now" must be heavier than "improve".
		foreach ( self::DEVICES as $device ) {
			if ( $out[ 'image_error_kb_' . $device ] <= $out[ 'image_warn_kb_' . $device ] ) {
				$out[ 'image_error_kb_' . $device ] = $out[ 'image_warn_kb_' . $device ] + 100;
			}
		}

		return $out;
	}

	private static function clamp( mixed $value, int|float $min, int|float $max, bool $float ): int|float {
		if ( $float ) {
			$value = is_numeric( $value ) ? round( (float) $value, 1 ) : (float) $min;
			return max( (float) $min, min( (float) $max, $value ) );
		}
		$value = is_numeric( $value ) ? (int) $value : (int) $min;
		return max( (int) $min, min( (int) $max, $value ) );
	}
}
