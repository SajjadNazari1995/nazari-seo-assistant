<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (one option) with defaults and sanitizing.
 */
final class NZSA_Settings {

	public const OPTION = 'nzsa_settings';

	/**
	 * @return array<string,int|float>
	 */
	public static function defaults(): array {
		return array(
			'image_warn_kb'       => 200,
			'image_error_kb'      => 500,
			'lcp_kb'              => 150,
			'retina_factor'       => 2.0,
			'max_dom_depth'       => 6,
			'viewport'            => 1440,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * @return array<string,int|float>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? $saved : array() );
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
		$d = self::defaults();

		$warn  = self::clamp_int( $input['image_warn_kb'] ?? $d['image_warn_kb'], 20, 5000 );
		$error = self::clamp_int( $input['image_error_kb'] ?? $d['image_error_kb'], 20, 20000 );
		if ( $error <= $warn ) {
			$error = $warn + 100;
		}

		$retina = isset( $input['retina_factor'] ) && is_numeric( $input['retina_factor'] ) ? (float) $input['retina_factor'] : $d['retina_factor'];

		return array(
			'image_warn_kb'       => $warn,
			'image_error_kb'      => $error,
			'lcp_kb'              => self::clamp_int( $input['lcp_kb'] ?? $d['lcp_kb'], 30, 2000 ),
			'retina_factor'       => max( 1.0, min( 3.0, round( $retina, 1 ) ) ),
			'max_dom_depth'       => self::clamp_int( $input['max_dom_depth'] ?? $d['max_dom_depth'], 3, 15 ),
			'viewport'            => self::clamp_int( $input['viewport'] ?? $d['viewport'], 1024, 2560 ),
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);
	}

	private static function clamp_int( mixed $value, int $min, int $max ): int {
		$value = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $value ) );
	}
}
