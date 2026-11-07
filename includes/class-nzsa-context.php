<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Everything a rule needs to know about the analysis that is running.
 */
final class NZSA_Context {

	/** @var NZSA_Node[]|null */
	private ?array $all = null;

	/** @var array<int,array{node:NZSA_Node,level:int,text:string}>|null */
	private ?array $headings = null;

	/**
	 * @param NZSA_Node[]          $roots          Top-level elements being analysed.
	 * @param string              $scope          "element" (one clicked element) or "page".
	 * @param int                 $position_index Index of the analysed element on the page (element scope).
	 * @param int                 $position_total Number of top-level elements on the page (element scope).
	 * @param array<string,mixed> $settings       Plugin settings.
	 * @param string              $role           What the page is for (see NZSA_Roles).
	 * @param NZSA_Measure|null   $measure        Sizes measured in the editor preview, when sent.
	 */
	public function __construct(
		public readonly array $roots,
		public readonly string $scope,
		public readonly int $position_index,
		public readonly int $position_total,
		public readonly array $settings,
		public readonly NZSA_Images $images,
		public readonly int $post_id = 0,
		public readonly string $role = 'page',
		public readonly ?NZSA_Measure $measure = null
	) {}

	/** True for header, footer and popup templates. */
	public function is_fragment(): bool {
		return NZSA_Roles::is_fragment( $this->role );
	}

	/** @return NZSA_Node[] Every node in document order. */
	public function all(): array {
		if ( null === $this->all ) {
			$this->all = array();
			foreach ( $this->roots as $root ) {
				$this->all[] = $root;
				array_push( $this->all, ...$root->descendants() );
			}
		}
		return $this->all;
	}

	public function setting( string $key ): int|float {
		return $this->settings[ $key ] ?? 0;
	}

	/**
	 * Where a top-level element sits on the page.
	 *
	 * @return array{0:int,1:int} Index and total.
	 */
	public function position_of( NZSA_Node $root ): array {
		if ( 'page' === $this->scope ) {
			return array( $root->index, count( $this->roots ) );
		}
		return array( $this->position_index, max( 1, $this->position_total ) );
	}

	/**
	 * Headings (h1–h6) in document order, from Heading widgets and from HTML inside text widgets.
	 *
	 * @return array<int,array{node:NZSA_Node,level:int,text:string}>
	 */
	public function headings(): array {
		if ( null !== $this->headings ) {
			return $this->headings;
		}

		$this->headings = array();
		foreach ( $this->all() as $node ) {
			if ( ! $node->is_widget() ) {
				continue;
			}

			if ( in_array( $node->widget, array( 'heading', 'theme-post-title', 'theme-site-title', 'theme-page-title', 'theme-archive-title', 'woocommerce-product-title' ), true ) ) {
				$default = 'heading' === $node->widget ? 'h2' : 'h1';
				$tag     = strtolower( (string) $node->get( 'header_size', $default ) );
				if ( ! preg_match( '/^h([1-6])$/', $tag, $m ) ) {
					continue;
				}
				$text = 'heading' === $node->widget ? self::plain( (string) $node->get( 'title' ) ) : 'dynamic';
				$this->headings[] = array( 'node' => $node, 'level' => (int) $m[1], 'text' => $text );
			} elseif ( 'text-editor' === $node->widget ) {
				$html = (string) $node->get( 'editor' );
				if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
					foreach ( $matches as $m ) {
						$this->headings[] = array( 'node' => $node, 'level' => (int) $m[1], 'text' => self::plain( $m[2] ) );
					}
				}
			}
		}

		return $this->headings;
	}

	/** Visible text of an HTML snippet, whitespace collapsed. */
	public static function plain( string $html ): string {
		$text = wp_strip_all_tags( $html );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/** Shortens text for messages. */
	public static function excerpt( string $text, int $length = 40 ): string {
		return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length - 1 ) . '…' : $text;
	}
}
