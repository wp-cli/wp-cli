<?php

namespace WP_CLI;

/**
 * Generate a synopsis from a command's PHPdoc arguments.
 * Turns something like "<object-id>..."
 * into [ optional=>false, type=>positional, repeating=>true, name=>object-id ]
 *
 * @phpstan-import-type FlagParameter from \WP_CLI
 * @phpstan-import-type AssocParameter from \WP_CLI
 * @phpstan-import-type PositionalParameter from \WP_CLI
 * @phpstan-import-type GenericParameter from \WP_CLI
 * @phpstan-import-type UnknownParameter from \WP_CLI
 * @phpstan-import-type CommandSynopsis from \WP_CLI
 */
class SynopsisParser {

	/**
	 * @param string $synopsis A synopsis
	 * @return array List of parameters
	 */
	public static function parse( $synopsis ) {
		$tokens = array_filter( (array) preg_split( '/[\s\t]+/', $synopsis ) );

		$params = [];
		foreach ( $tokens as $token ) {
			$param = self::classify_token( $token );

			// Some types of parameters shouldn't be mandatory
			if ( isset( $param['optional'] ) && ! $param['optional'] ) {
				if ( 'flag' === $param['type'] || ( 'assoc' === $param['type'] && $param['value']['optional'] ) ) {
					$param['type'] = 'unknown';
				}
			}

			$param['token'] = $token;
			$params[]       = $param;
		}

		return $params;
	}

	/**
	 * Render the Synopsis into a format string.
	 *
	 * @param array $synopsis A structured synopsis. This might get reordered
	 *                        to match the parsed output.
	 * @return string Rendered synopsis.
	 *
	 * @phpstan-param CommandSynopsis[] $synopsis
	 */
	public static function render( &$synopsis ) {
		if ( ! is_array( $synopsis ) ) {
			return '';
		}
		$bits               = [
			'positional' => '',
			'assoc'      => '',
			'generic'    => '',
			'flag'       => '',
		];
		$reordered_synopsis = [
			'positional' => [],
			'assoc'      => [],
			'generic'    => [],
			'flag'       => [],
		];
		foreach ( $bits as $key => &$value ) {
			foreach ( $synopsis as $arg ) {
				if ( empty( $arg['type'] )
					|| $key !== $arg['type'] ) {
					continue;
				}

				if ( empty( $arg['name'] ) && 'generic' !== $arg['type'] ) {
					continue;
				}

				if ( 'positional' === $key ) {
					/**
					 * @phpstan-var PositionalParameter $arg
					 */
					$rendered_arg = "<{$arg['name']}>";

					$reordered_synopsis['positional'] [] = $arg;
				} elseif ( 'assoc' === $key ) {
					/**
					 * @phpstan-var AssocParameter $arg
					 */
					$arg_value = isset( $arg['value']['name'] ) ? $arg['value']['name'] : $arg['name'];
					$arg_value = "=<{$arg_value}>";

					if ( ! empty( $arg['value']['optional'] ) ) {
						$arg_value = "[{$arg_value}]";
					}

					$alias_suffix = '';
					if ( ! empty( $arg['aliases'] ) ) {
						$alias_suffix = '|' . implode( '|', $arg['aliases'] );
					}

					$rendered_arg = "--{$arg['name']}{$arg_value}{$alias_suffix}";

					$reordered_synopsis['assoc'] [] = $arg;
				} elseif ( 'generic' === $key ) {
					$rendered_arg = '--<field>=<value>';

					$reordered_synopsis['generic'] [] = $arg;
				} elseif ( 'flag' === $key ) {
					/**
					 * @phpstan-var FlagParameter $arg
					 */
					$alias_suffix = '';
					if ( ! empty( $arg['aliases'] ) ) {
						$alias_suffix = '|' . implode( '|', $arg['aliases'] );
					}

					$rendered_arg = "--{$arg['name']}{$alias_suffix}";

					$reordered_synopsis['flag'] [] = $arg;
				}
				if ( ! empty( $arg['repeating'] ) ) {
					$rendered_arg = "{$rendered_arg}...";
				}
				if ( ! empty( $arg['optional'] ) ) {
					$rendered_arg = "[{$rendered_arg}]";
				}
				$value .= "{$rendered_arg} ";
			}
		}
		$rendered = implode( '', $bits );

		$synopsis = array_merge(
			$reordered_synopsis['positional'],
			$reordered_synopsis['assoc'],
			$reordered_synopsis['generic'],
			$reordered_synopsis['flag']
		);

		return rtrim( $rendered, ' ' );
	}

	/**
	 * Classify argument attributes based on its syntax.
	 *
	 * @param string $token
	 * @return array
	 *
	 * @phpstan-return CommandSynopsis
	 */
	private static function classify_token( $token ) {
		list( $optional, $token )  = self::is_optional( $token );
		list( $repeating, $token ) = self::is_repeating( $token );
		list( $aliases, $token )   = self::extract_aliases( $token );

		$p_name  = '([a-z-_0-9]+)';
		$p_value = '([a-zA-Z-_|,0-9]+)';

		if ( '--<field>=<value>' === $token ) {
			return [
				'type'      => 'generic',
				'optional'  => $optional,
				'repeating' => $repeating,
			];
		} elseif ( preg_match( "/^<($p_value)>$/", $token, $matches ) ) {
			return [
				'type'      => 'positional',
				'name'      => $matches[1],
				'optional'  => $optional,
				'repeating' => $repeating,
			];
		} elseif ( preg_match( "/^--(?:\\[no-\\])?$p_name/", $token, $matches ) ) {
			$name  = $matches[1];
			$value = substr( $token, strlen( $matches[0] ) );

			// substr can return false <= PHP 8.0.
			// @phpstan-ignore identical.alwaysFalse
			if ( false === $value || '' === $value ) {
				$param = [
					'type'      => 'flag',
					'name'      => $name,
					'optional'  => $optional,
					'repeating' => $repeating,
				];
				if ( ! empty( $aliases ) ) {
					$param['aliases'] = $aliases;
				}
				return $param;
			} else {
				list( $value_optional, $value ) = self::is_optional( $value );

				if ( preg_match( "/^=<$p_value>$/", $value, $matches_value ) ) {
					$param = [
						'type'      => 'assoc',
						'name'      => $name,
						'optional'  => $optional,
						'repeating' => $repeating,
						'value'     => [
							'optional' => $value_optional,
							'name'     => $matches_value[1],
						],
					];
					if ( ! empty( $aliases ) ) {
						$param['aliases'] = $aliases;
					}
					return $param;
				}
			}
		}

		return [
			'type'      => 'unknown',
			'optional'  => $optional,
			'repeating' => $repeating,
		];
	}

	/**
	 * Extract pipe-separated aliases from a token.
	 *
	 * Given `--flag|alias1|alias2`, returns `[['alias1', 'alias2'], '--flag']`.
	 * The `|` separator inside `<value>` brackets is ignored.
	 *
	 * @param string $token
	 * @return array{0: string[], 1: string}
	 */
	private static function extract_aliases( $token ) {
		$depth = 0;
		$len   = strlen( $token );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = $token[ $i ];
			if ( '<' === $char ) {
				++$depth;
			} elseif ( '>' === $char ) {
				--$depth;
			} elseif ( '|' === $char && 0 === $depth ) {
				$aliases = array_values(
					array_filter(
						explode( '|', substr( $token, $i + 1 ) ),
						static function ( $alias ) {
							return '' !== $alias;
						}
					)
				);
				return [ $aliases, substr( $token, 0, $i ) ];
			}
		}

		return [ [], $token ];
	}

	/**
	 * An optional parameter is surrounded by square brackets.
	 *
	 * @param string $token
	 * @return array{0: bool, 1: string}
	 */
	private static function is_optional( $token ) {
		if ( '[' === substr( $token, 0, 1 ) && ']' === substr( $token, -1 ) ) {
			return [ true, substr( $token, 1, -1 ) ];
		}

		return [ false, $token ];
	}

	/**
	 * A repeating parameter is followed by an ellipsis.
	 *
	 * @param string $token
	 * @return array{0: bool, 1: string}
	 */
	private static function is_repeating( $token ) {
		if ( '...' === substr( $token, -3 ) ) {
			return [ true, substr( $token, 0, -3 ) ];
		}

		return [ false, $token ];
	}
}
