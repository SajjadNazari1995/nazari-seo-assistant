<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Base class of every check. To add your own, extend it and register it with the
 * `nzsa_rules` filter (see README, "Developer hooks").
 */
abstract class NZSA_Rule {

	/** Unique id, used in reports. */
	abstract public function id(): string;

	/**
	 * Checks one element. Called once for every element, in document order.
	 *
	 * @return NZSA_Issue[]
	 */
	public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
		return array();
	}

	/**
	 * Checks that need the whole analysed tree (for example "one H1 per page").
	 *
	 * @return NZSA_Issue[]
	 */
	public function check_page( NZSA_Context $ctx ): array {
		return array();
	}

	protected function issue(
		NZSA_Node $node,
		NZSA_Category $category,
		NZSA_Severity $severity,
		string $suffix,
		string $message,
		string $fix = '',
		array $data = array()
	): NZSA_Issue {
		return new NZSA_Issue( $this->id() . '.' . $suffix, $category, $severity, $node->id, $message, $fix, $data );
	}
}
