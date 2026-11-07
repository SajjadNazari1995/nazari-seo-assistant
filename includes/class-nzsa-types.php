<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * How serious a finding is. The weight is the number of "penalty units" it costs.
 */
enum NZSA_Severity: string {
	case Error   = 'error';
	case Warning = 'warning';
	case Info    = 'info';

	public function weight(): float {
		return match ( $this ) {
			self::Error   => 3.0,
			self::Warning => 1.5,
			self::Info    => 0.5,
		};
	}

	public function label(): string {
		return match ( $this ) {
			self::Error   => __( 'Fix', 'seo-assistant-for-elementor' ),
			self::Warning => __( 'Improve', 'seo-assistant-for-elementor' ),
			self::Info    => __( 'Tip', 'seo-assistant-for-elementor' ),
		};
	}
}

/**
 * Score categories. Points add up to 100.
 */
enum NZSA_Category: string {
	case Tags       = 'tags';
	case Dimensions = 'dimensions';
	case Speed      = 'speed';
	case Access     = 'access';

	public function max_points(): int {
		return match ( $this ) {
			self::Tags       => 20,
			self::Dimensions => 20,
			self::Speed      => 30,
			self::Access     => 30,
		};
	}

	/** Penalty units that bring the category to zero. */
	public function budget(): float {
		return match ( $this ) {
			self::Tags       => 12.0,
			self::Dimensions => 12.0,
			self::Speed      => 15.0,
			self::Access     => 12.0,
		};
	}

	public function label(): string {
		return match ( $this ) {
			self::Tags       => __( 'HTML tags', 'seo-assistant-for-elementor' ),
			self::Dimensions => __( 'Dimensions', 'seo-assistant-for-elementor' ),
			self::Speed      => __( 'Loading speed', 'seo-assistant-for-elementor' ),
			self::Access     => __( 'Content and accessibility', 'seo-assistant-for-elementor' ),
		};
	}
}

/**
 * One finding, tied to one element.
 */
final class NZSA_Issue {

	/**
	 * @param array<string,scalar> $data Extra machine-readable details (sizes, counts…).
	 */
	public function __construct(
		public readonly string $rule,
		public readonly NZSA_Category $category,
		public readonly NZSA_Severity $severity,
		public readonly string $node_id,
		public readonly string $message,
		public readonly string $fix = '',
		public readonly array $data = array()
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'rule'     => $this->rule,
			'category' => $this->category->value,
			'severity' => $this->severity->value,
			'label'    => $this->severity->label(),
			'message'  => $this->message,
			'fix'      => $this->fix,
			'data'     => $this->data,
		);
	}
}

/**
 * One Elementor element of the tree being analysed (section, container, column or widget).
 */
final class NZSA_Node {

	/** @var NZSA_Node[] */
	public array $children = array();

	/** Estimated width in CSS pixels at desktop size. */
	public float $width = 0.0;

	/** Position among all nodes (document order). */
	public int $order = 0;

	/** Width in CSS px measured in the editor preview in the current device view (null when not measured). */
	public ?float $measured = null;

	/** Measured drawn width of the element's first image, desktop view only (null when not measured). */
	public ?float $img_width = null;

	/**
	 * @param array<string,mixed> $settings
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $widget,
		public readonly array $settings,
		public readonly ?NZSA_Node $parent,
		public readonly int $depth,
		public readonly int $index
	) {}

	public function is_widget(): bool {
		return 'widget' === $this->type;
	}

	public function is_container(): bool {
		return in_array( $this->type, array( 'section', 'container', 'column' ), true );
	}

	/** Setting value (empty string when absent). */
	public function get( string $key, mixed $default = '' ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	/** Slider / dimension style value: numeric `size` or null. */
	public function size( string $key ): ?float {
		$value = $this->settings[ $key ] ?? null;
		if ( is_array( $value ) && isset( $value['size'] ) && '' !== $value['size'] && is_numeric( $value['size'] ) ) {
			return (float) $value['size'];
		}
		return null;
	}

	public function unit( string $key ): string {
		$value = $this->settings[ $key ] ?? null;
		return is_array( $value ) && isset( $value['unit'] ) ? (string) $value['unit'] : 'px';
	}

	/** True when a setting has a value for the given device suffix ("mobile", "tablet"). */
	public function has_device( string $key, string $device ): bool {
		$value = $this->settings[ $key . '_' . $device ] ?? null;
		if ( is_array( $value ) ) {
			return isset( $value['size'] ) && '' !== $value['size'];
		}
		return null !== $value && '' !== $value;
	}

	/**
	 * Every descendant, depth first, in document order.
	 *
	 * @return NZSA_Node[]
	 */
	public function descendants(): array {
		$list = array();
		foreach ( $this->children as $child ) {
			$list[] = $child;
			array_push( $list, ...$child->descendants() );
		}
		return $list;
	}
}
