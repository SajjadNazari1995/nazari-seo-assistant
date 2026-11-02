<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Turns the element JSON sent by the Elementor editor into a tree of NZSA_Node objects
 * and estimates how wide (in CSS px, on desktop) every element is drawn.
 *
 * The data is untrusted: it is only read, never printed back as HTML.
 */
final class NZSA_Tree {

	public const MAX_NODES = 3000;
	public const MAX_DEPTH = 30;

	private int $count = 0;

	/**
	 * @param int $kit_width Default boxed content width from the Elementor kit.
	 * @param int $viewport  Desktop viewport used for full-width elements.
	 */
	public function __construct(
		private readonly int $kit_width,
		private readonly int $viewport
	) {}

	/**
	 * @param array<int,mixed> $elements Top-level elements.
	 * @return NZSA_Node[]
	 */
	public function build( array $elements ): array {
		$this->count = 0;
		$roots       = array();
		$index       = 0;

		foreach ( $elements as $raw ) {
			$node = $this->make( $raw, null, 0, $index );
			if ( $node ) {
				$node->width = $this->root_width( $node );
				$this->size_children( $node );
				$roots[] = $node;
				++$index;
			}
		}

		$order = 0;
		foreach ( $roots as $root ) {
			$this->number( $root, $order );
		}

		return $roots;
	}

	private function number( NZSA_Node $node, int &$order ): void {
		$node->order = $order++;
		foreach ( $node->children as $child ) {
			$this->number( $child, $order );
		}
	}

	private function make( mixed $raw, ?NZSA_Node $parent, int $depth, int $index ): ?NZSA_Node {
		if ( ! is_array( $raw ) || $depth > self::MAX_DEPTH || $this->count >= self::MAX_NODES ) {
			return null;
		}

		++$this->count;

		$id = isset( $raw['id'] ) && is_scalar( $raw['id'] ) ? (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $raw['id'] ) : '';
		$id = '' !== $id ? substr( $id, 0, 40 ) : 'n' . $this->count;

		$el_type = isset( $raw['elType'] ) && is_string( $raw['elType'] ) ? sanitize_key( $raw['elType'] ) : '';
		$widget  = isset( $raw['widgetType'] ) && is_string( $raw['widgetType'] ) ? sanitize_key( $raw['widgetType'] ) : '';
		$kids    = isset( $raw['elements'] ) && is_array( $raw['elements'] ) ? $raw['elements'] : array();

		if ( 'widget' === $el_type || ( '' === $el_type && '' !== $widget ) ) {
			$type = 'widget';
		} elseif ( in_array( $el_type, array( 'section', 'column', 'container' ), true ) ) {
			$type = $el_type;
		} else {
			// Unknown element kinds (for example newer atomic elements) are treated by what they hold.
			$type = $kids ? 'container' : 'widget';
		}

		$settings = isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array();

		$node = new NZSA_Node( $id, $type, 'widget' === $type ? $widget : '', $settings, $parent, $depth, $index );

		$child_index = 0;
		foreach ( $kids as $child_raw ) {
			$child = $this->make( $child_raw, $node, $depth + 1, $child_index );
			if ( $child ) {
				$node->children[] = $child;
				++$child_index;
			}
		}

		return $node;
	}

	/* ------------------------------------------------------------------ *
	 * Width estimate
	 * ------------------------------------------------------------------ */

	private function root_width( NZSA_Node $node ): float {
		if ( 'section' === $node->type ) {
			if ( 'full_width' === $node->get( 'layout' ) ) {
				return (float) $this->viewport;
			}
			$size = $node->size( 'content_width' );
			return $size && 'px' === $node->unit( 'content_width' ) ? $size : (float) $this->kit_width;
		}

		if ( 'full' === $node->get( 'content_width' ) ) {
			return (float) $this->viewport;
		}
		$size = $node->size( 'boxed_width' );
		return $size && 'px' === $node->unit( 'boxed_width' ) ? $size : (float) $this->kit_width;
	}

	private function size_children( NZSA_Node $node ): void {
		$count = count( $node->children );
		if ( ! $count ) {
			return;
		}

		$row = 'container' === $node->type && in_array( $node->get( 'flex_direction' ), array( 'row', 'row-reverse' ), true );

		foreach ( $node->children as $child ) {
			$child->width = $this->child_width( $node, $child, $count, $row );
			$this->size_children( $child );
		}
	}

	private function child_width( NZSA_Node $parent, NZSA_Node $child, int $count, bool $row ): float {
		$available = max( 1.0, $parent->width );

		// Columns of a classic section: a percentage of the row.
		if ( 'column' === $child->type ) {
			$pct = $child->get( '_column_size' );
			$pct = is_numeric( $pct ) && (float) $pct > 0 ? (float) $pct : 100.0 / $count;
			return $available * min( 100.0, $pct ) / 100;
		}

		// Elements that set their own width.
		$own = $child->size( 'width' );
		if ( null === $own && 'custom' === $child->get( '_element_width' ) ) {
			$own = $child->size( '_element_custom_width' );
			$unit = $child->unit( '_element_custom_width' );
		} else {
			$unit = $child->unit( 'width' );
		}
		if ( null !== $own && $own > 0 ) {
			return '%' === $unit ? $available * min( 100.0, $own ) / 100 : $own;
		}

		$width = $row ? $available / $count : $available;

		// Carousels and galleries show several images side by side.
		if ( 'widget' === $child->type ) {
			$per_row = 1;
			if ( 'image-carousel' === $child->widget ) {
				$per_row = max( 1, (int) ( $child->get( 'slides_to_show', 3 ) ?: 3 ) );
			} elseif ( 'image-gallery' === $child->widget || 'gallery' === $child->widget ) {
				$per_row = max( 1, (int) ( $child->get( 'gallery_columns', 4 ) ?: 4 ) );
			}
			$width /= $per_row;
		}

		return $width;
	}
}
