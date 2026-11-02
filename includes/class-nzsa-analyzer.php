<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Runs every rule over the elements sent by the editor and builds the report:
 * findings per element, a score per category, and the most important things to fix.
 */
final class NZSA_Analyzer {

	/**
	 * Rules in the order they run. Add your own with the `nzsa_rules` filter.
	 *
	 * @return NZSA_Rule[]
	 */
	public static function rules(): array {
		$rules = array(
			new NZSA_Rule_Tags(),
			new NZSA_Rule_Images(),
			new NZSA_Rule_Weight(),
			new NZSA_Rule_Responsive(),
			new NZSA_Rule_Links(),
		);

		/**
		 * Filters the list of rules.
		 *
		 * @param NZSA_Rule[] $rules Rule objects.
		 */
		$filtered = apply_filters( 'nzsa_rules', $rules );

		return array_values( array_filter( is_array( $filtered ) ? $filtered : $rules, static fn( $rule ): bool => $rule instanceof NZSA_Rule ) );
	}

	/**
	 * @param array<int,mixed> $elements       Element JSON from the editor (the clicked element, or the whole page).
	 * @param string           $scope          "element" or "page".
	 * @param int              $position_index Index of the clicked element among the page's top-level elements.
	 * @param int              $position_total Number of top-level elements on the page.
	 * @param int              $post_id        Page being edited (0 if unknown).
	 * @return array<string,mixed>
	 */
	public function analyze( array $elements, string $scope = 'element', int $position_index = 0, int $position_total = 1, int $post_id = 0 ): array {
		$settings = NZSA_Settings::all();

		$tree  = new NZSA_Tree( self::kit_width(), (int) $settings['viewport'] );
		$roots = $tree->build( $elements );

		$ctx = new NZSA_Context(
			$roots,
			'page' === $scope ? 'page' : 'element',
			max( 0, $position_index ),
			max( 1, $position_total ),
			$settings,
			new NZSA_Images(),
			$post_id
		);

		/** @var NZSA_Issue[] $issues */
		$issues = array();
		foreach ( self::rules() as $rule ) {
			foreach ( $ctx->all() as $node ) {
				array_push( $issues, ...$rule->check( $ctx, $node ) );
			}
			array_push( $issues, ...$rule->check_page( $ctx ) );
		}

		return $this->report( $ctx, $issues );
	}

	/**
	 * @param NZSA_Issue[] $issues
	 * @return array<string,mixed>
	 */
	private function report( NZSA_Context $ctx, array $issues ): array {
		// Same finding on the same element is reported once.
		$unique = array();
		foreach ( $issues as $issue ) {
			$unique[ $issue->node_id . '|' . $issue->rule . '|' . $issue->message ] = $issue;
		}
		$issues = array_values( $unique );

		// Findings per element, most serious first.
		$by_node = array();
		foreach ( $issues as $issue ) {
			$by_node[ $issue->node_id ][] = $issue;
		}
		$nodes = array();
		foreach ( $ctx->all() as $node ) {
			if ( empty( $by_node[ $node->id ] ) ) {
				continue;
			}
			$list = $by_node[ $node->id ];
			usort( $list, static fn( NZSA_Issue $a, NZSA_Issue $b ): int => $b->severity->weight() <=> $a->severity->weight() );
			$nodes[] = array(
				'id'     => $node->id,
				'issues' => array_map( static fn( NZSA_Issue $i ): array => $i->to_array(), $list ),
			);
		}

		// Score: every category starts full and loses points for each finding.
		$categories = array();
		$total      = 0;
		$max        = 0;
		foreach ( NZSA_Category::cases() as $category ) {
			$penalty = 0.0;
			foreach ( $issues as $issue ) {
				if ( $issue->category === $category ) {
					$penalty += $issue->severity->weight();
				}
			}
			$points      = (int) round( $category->max_points() * max( 0.0, 1.0 - $penalty / $category->budget() ) );
			$total      += $points;
			$max        += $category->max_points();
			$categories[] = array(
				'id'     => $category->value,
				'label'  => $category->label(),
				'points' => $points,
				'max'    => $category->max_points(),
			);
		}

		$counts = array( 'error' => 0, 'warning' => 0, 'info' => 0 );
		foreach ( $issues as $issue ) {
			++$counts[ $issue->severity->value ];
		}

		// The five things worth doing first.
		$ranked = $issues;
		usort( $ranked, static fn( NZSA_Issue $a, NZSA_Issue $b ): int => $b->severity->weight() <=> $a->severity->weight() );
		$top = array();
		foreach ( array_slice( $ranked, 0, 5 ) as $issue ) {
			$top[] = array(
				'node'     => $issue->node_id,
				'severity' => $issue->severity->value,
				'message'  => $issue->message,
				'fix'      => $issue->fix,
			);
		}

		return array(
			'scope'   => $ctx->scope,
			'summary' => array(
				'total'      => $total,
				'max'        => $max,
				'categories' => $categories,
				'counts'     => $counts,
				'top'        => $top,
			),
			'nodes'   => $nodes,
			'stats'   => $this->stats( $ctx ),
		);
	}

	/**
	 * @return array<string,int>
	 */
	private function stats( NZSA_Context $ctx ): array {
		$seen   = array();
		$images = 0;
		$bytes  = 0;
		foreach ( $ctx->all() as $node ) {
			foreach ( NZSA_Image_Refs::of( $node ) as $ref ) {
				++$images;
				$info = $ctx->images->info( $ref['source'], (string) $ref['size'], $ref['custom'] );
				if ( $info ) {
					$key = $info['id'] . '|' . $ref['size'];
					if ( ! isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$bytes       += (int) $info['bytes'];
					}
				}
			}
		}

		return array(
			'elements' => count( $ctx->all() ),
			'images'   => $images,
			'kb'       => (int) round( $bytes / 1024 ),
		);
	}

	/** Default boxed width from the Elementor kit (Site Settings → Layout), 1140 px when unknown. */
	private static function kit_width(): int {
		$width = 1140;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
			if ( $kit ) {
				$value = $kit->get_settings( 'container_width' );
				if ( is_array( $value ) && ! empty( $value['size'] ) && is_numeric( $value['size'] ) && 'px' === ( $value['unit'] ?? 'px' ) ) {
					$width = (int) $value['size'];
				}
			}
		}
		return max( 320, min( 2560, $width ) );
	}
}
