<?php

namespace WP_CLI;

/**
 * Checks if the list of parameters matches the specification defined in the synopsis.
 *
 * @phpstan-import-type CommandSynopsis from \WP_CLI
 */
class SynopsisValidator {

	/**
	 * Structured representation of command synopsis.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $spec;

	/**
	 * @param string $synopsis Command's synopsis.
	 */
	public function __construct( $synopsis ) {
		$this->spec = SynopsisParser::parse( $synopsis );
	}

	/**
	 * Get any unknown arguments.
	 *
	 * @return array<int, string>
	 */
	public function get_unknown() {
		return array_map(
			function ( $v ) {
				return is_scalar( $v ) ? (string) $v : '';
			},
			array_column(
				$this->query_spec(
					[
						'type' => 'unknown',
					]
				),
				'token'
			)
		);
	}

	/**
	 * Check whether there are enough positional arguments.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return bool
	 */
	public function enough_positionals( $args ) {
		$positional = $this->query_spec(
			[
				'type'     => 'positional',
				'optional' => false,
			]
		);

		return count( $args ) >= count( $positional );
	}

	/**
	 * Check for any unknown positionals.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return array<int, string>
	 */
	public function unknown_positionals( $args ) {
		$positional_repeating = $this->query_spec(
			[
				'type'      => 'positional',
				'repeating' => true,
			]
		);

		// At least one positional supports as many as possible.
		if ( ! empty( $positional_repeating ) ) {
			return [];
		}

		$positional = $this->query_spec(
			[
				'type'      => 'positional',
				'repeating' => false,
			]
		);

		return array_slice( $args, count( $positional ) );
	}

	/**
	 * Check that all required keys are present and that they have values.
	 *
	 * @param array<string, mixed> $assoc_args Parameters passed to command.
	 * @return array{0: array{fatal: array<string, string>, warning: array<string, string>}, 1: array<int, string>}
	 */
	public function validate_assoc( $assoc_args ) {
		$assoc_spec = $this->query_spec(
			[
				'type' => 'assoc',
			]
		);

		$errors = [
			'fatal'   => [],
			'warning' => [],
		];

		$to_unset = [];

		foreach ( $assoc_spec as $param ) {
			$key = is_scalar( $param['name'] ) ? (string) $param['name'] : '';

			if ( ! isset( $assoc_args[ $key ] ) ) {
				if ( ! $param['optional'] ) {
					$errors['fatal'][ $key ] = "missing --$key parameter";
				}
			} elseif ( true === $assoc_args[ $key ] && is_array( $param['value'] ) && ! $param['value']['optional'] ) {
					$error_type                    = ( ! $param['optional'] ) ? 'fatal' : 'warning';
					$errors[ $error_type ][ $key ] = "--$key parameter needs a value";

					$to_unset[] = $key;
			}
		}

		return [ $errors, $to_unset ];
	}

	/**
	 * Check whether there are unknown parameters supplied.
	 *
	 * @param array<string, mixed> $assoc_args Parameters passed to command.
	 * @return array<int, string>
	 */
	public function unknown_assoc( $assoc_args ) {
		$generic = $this->query_spec(
			[
				'type' => 'generic',
			]
		);

		if ( count( $generic ) ) {
			return [];
		}

		$known_assoc = [];

		foreach ( $this->spec as $param ) {
			if ( in_array( $param['type'], [ 'assoc', 'flag' ], true ) && is_string( $param['name'] ) ) {
				$known_assoc[] = $param['name'];
			}
		}

		return array_values( array_diff( array_map( 'strval', array_keys( $assoc_args ) ), $known_assoc ) );
	}

	/**
	 * Filters a list of associative arrays, based on a set of key => value arguments.
	 *
	 * @param array<string, mixed> $args An array of key => value arguments to match against
	 * @param string               $operator
	 * @return array<int, array<string, mixed>>
	 */
	private function query_spec( $args, $operator = 'AND' ) {
		$operator = strtoupper( $operator );
		$count    = count( $args );
		$filtered = [];

		foreach ( $this->spec as $key => $to_match ) {
			$matched = 0;
			foreach ( $args as $m_key => $m_value ) {
				if ( array_key_exists( $m_key, $to_match ) && $m_value === $to_match[ $m_key ] ) {
					++$matched;
				}
			}

			if ( ( 'AND' === $operator && $matched === $count )
				|| ( 'OR' === $operator && $matched > 0 )
				|| ( 'NOT' === $operator && 0 === $matched ) ) {
					$filtered[ $key ] = $to_match;
			}
		}

		return $filtered;
	}
}
