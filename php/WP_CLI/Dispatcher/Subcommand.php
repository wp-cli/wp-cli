<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;
use WP_CLI\DocParser;
use WP_CLI\SynopsisParser;
use WP_CLI\SynopsisValidator;
use WP_CLI\Utils;

/**
 * A leaf node in the command tree.
 *
 * @package WP_CLI
 */
class Subcommand extends CompositeCommand {

	/**
	 * Alias for the subcommand.
	 *
	 * @var string
	 */
	private $alias;

	/**
	 * Callable to execute when the subcommand is invoked.
	 *
	 * @var callable
	 */
	private $when_invoked;

	/**
	 * Initiate a new Subcommand.
	 *
	 * @param RootCommand|CompositeCommand $parent       Parent command.
	 * @param string                       $name         Command name.
	 * @param DocParser                    $docparser    DocParser instance.
	 * @param callable                     $when_invoked Invocation callback.
	 */
	public function __construct( $parent, $name, $docparser, $when_invoked ) {
		$this->alias = $docparser->get_tag( 'alias' );

		parent::__construct( $parent, $name, $docparser );

		$this->when_invoked = $when_invoked;

		$this->synopsis = $docparser->get_synopsis();
		if ( ! $this->synopsis && $this->longdesc ) {
			$this->synopsis = self::extract_synopsis( $this->longdesc );
		}
	}

	/**
	 * Extract the synopsis from PHPdoc string.
	 *
	 * @param string $longdesc Command docs via PHPdoc
	 * @return string
	 */
	private static function extract_synopsis( $longdesc ) {
		preg_match_all( '/(.+?)[\r\n]+:/', $longdesc, $matches );
		return implode( ' ', $matches[1] );
	}

	/**
	 * Subcommands can't have subcommands because they
	 * represent code to be executed.
	 *
	 * @return bool
	 */
	public function can_have_subcommands() {
		return false;
	}

	/**
	 * Get the synopsis string for this subcommand.
	 * A synopsis defines what runtime arguments are
	 * expected, useful to humans and argument validation.
	 *
	 * @return string
	 */
	public function get_synopsis() {
		return $this->synopsis;
	}

	/**
	 * Set the synopsis string for this subcommand.
	 *
	 * @param string $synopsis
	 * @return void
	 */
	public function set_synopsis( $synopsis ) {
		$this->synopsis = $synopsis;
	}

	/**
	 * If an alias is set, grant access to it.
	 * Aliases permit subcommands to be instantiated
	 * with a secondary identity.
	 *
	 * @return string
	 */
	public function get_alias() {
		return $this->alias;
	}

	/**
	 * Print the usage details to the end user.
	 *
	 * @param string $prefix
	 * @return void
	 */
	public function show_usage( $prefix = 'usage: ' ) {
		\WP_CLI::line( $this->get_usage( $prefix ) );
	}

	/**
	 * Get the usage of the subcommand as a formatted string.
	 *
	 * @param string $prefix
	 * @return string
	 */
	public function get_usage( $prefix ) {
		return sprintf(
			'%s%s %s',
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		);
	}

	/**
	 * Wrapper for CLI Tools' prompt() method.
	 *
	 * @param string $question
	 * @param mixed $default
	 * @return string|false
	 */
	private function prompt( $question, $default = null ) {

		$question .= ': ';
		if ( function_exists( 'readline' ) ) {
			return readline( $question );
		}

		echo $question;

		$ret = (string) stream_get_line( STDIN, 1024, "\n" );
		if ( Utils\is_windows() && "\r" === substr( $ret, -1 ) ) {
			$ret = substr( $ret, 0, -1 );
		}
		return $ret;
	}

	/**
	 * Get the description for an argument from documentation.
	 *
	 * @param array<string, mixed> $spec_arg Argument specification from SynopsisParser
	 * @param DocParser            $docparser DocParser instance for retrieving descriptions
	 * @param string               $longdesc Long description text for regex matching
	 * @return string Description text, or empty string if not found
	 */
	private function get_arg_description( $spec_arg, $docparser, $longdesc ) {
		$description = '';
		$name        = is_string( $spec_arg['name'] ) ? $spec_arg['name'] : '';

		if ( 'positional' === $spec_arg['type'] ) {
			$description = $docparser->get_arg_desc( $name );
			// If get_arg_desc doesn't find it (e.g., for simple <arg> without modifiers),
			// try a simpler pattern that matches <arg> followed by : description,
			// using a pattern consistent with DocParser::get_arg_desc().
			if ( empty( $description ) ) {
				$arg_pattern = '/\[?<' . preg_quote( $name, '/' ) . ">.*\n:\s*(.+?)(\n|$)/";
				if ( preg_match( $arg_pattern, $longdesc, $matches ) ) {
					$description = trim( $matches[1] );
				}
			}
		} elseif ( 'assoc' === $spec_arg['type'] ) {
			$description = $docparser->get_param_desc( $name );
		} elseif ( 'flag' === $spec_arg['type'] ) {
			// For flags, the pattern is [--flag] not [--flag=<value>]
			// So we need a custom regex pattern in the longdesc
			$flag_pattern = '/\[?--' . preg_quote( $name, '/' ) . "\]\s*\n:\s*(.+?)(\n|$)/";
			if ( preg_match( $flag_pattern, $longdesc, $matches ) ) {
				$description = trim( $matches[1] );
			}
		}

		return $description;
	}

	/**
	 * Interactively prompt the user for input
	 * based on defined synopsis and passed arguments.
	 *
	 * @param array<mixed>         $args
	 * @param array<string, mixed> $assoc_args
	 * @return array{0: array<mixed>, 1: array<string, mixed>}
	 */
	private function prompt_args( $args, $assoc_args ) {

		$synopsis = $this->get_synopsis();

		if ( ! $synopsis ) {
			return [ $args, $assoc_args ];
		}

		// Create a docparser to get default values and descriptions
		$docparser = $this->create_mock_docparser();

		// To skip the already provided positional arguments, we need to count
		// how many we had already received.
		$arg_index = 0;

		$spec = array_filter(
			SynopsisParser::parse( $synopsis ),
			function ( $spec_arg ) use ( $args, $assoc_args, &$arg_index ) {
				/** @var array<string, mixed> $spec_arg */
				$name = isset( $spec_arg['name'] ) && is_string( $spec_arg['name'] ) ? $spec_arg['name'] : '';
				switch ( $spec_arg['type'] ) {
					case 'positional':
						// Only prompt for the positional arguments that are not
						// yet provided, based purely on number.
						return $arg_index++ >= count( $args );
					case 'generic':
						// Always prompt for generic arguments.
						return true;
					case 'assoc':
					case 'flag':
					default:
						// Prompt for the specific flags that were not provided
						// yet, based on name.
						return ! isset( $assoc_args[ $name ] );
				}
			}
		);

		$spec = array_values( $spec );

		$prompt_args = WP_CLI::get_config( 'prompt' );
		if ( is_string( $prompt_args ) ) {
			$prompt_args = explode( ',', $prompt_args );
		}

		// Reuse the existing DocParser to retrieve argument descriptions.
		$docparser = $this->docparser;

		// 'positional' arguments are positional (aka zero-indexed)
		// so $args needs to be reset before prompting for new arguments
		$args = [];

		foreach ( $spec as $key => $spec_arg ) {
			/** @var array<string, mixed> $spec_arg */
			$spec_name = isset( $spec_arg['name'] ) && is_string( $spec_arg['name'] ) ? $spec_arg['name'] : '';

			// When prompting for specific arguments (e.g. --prompt=user_pass),
			// ignore all arguments that don't match.
			if ( is_array( $prompt_args ) ) {
				if ( 'assoc' !== $spec_arg['type'] ) {
					continue;
				}
				$matched = in_array( $spec_name, $prompt_args, true );
				if ( ! $matched && ! empty( $spec_arg['aliases'] ) && is_array( $spec_arg['aliases'] ) ) {
					foreach ( $spec_arg['aliases'] as $alias ) {
						if ( is_string( $alias ) && in_array( $alias, $prompt_args, true ) ) {
							$matched = true;
							break;
						}
					}
				}
				if ( ! $matched ) {
					continue;
				}
			}

			$current_prompt = ( $key + 1 ) . '/' . count( $spec ) . ' ';

			// 'generic' permits arbitrary key=value (e.g. [--<field>=<value>] )
			if ( 'generic' === $spec_arg['type'] ) {

				$token                           = isset( $spec_arg['token'] ) && is_string( $spec_arg['token'] ) ? $spec_arg['token'] : '';
				list( $key_token, $value_token ) = explode( '=', $token );

				$repeat = false;
				do {
					if ( ! $repeat ) {
						$key_prompt = $current_prompt . $key_token;
					} else {
						$key_prompt = str_repeat( ' ', strlen( $current_prompt ) ) . $key_token;
					}

					$key = $this->prompt( $key_prompt );
					if ( false === $key ) {
						return [ $args, $assoc_args ];
					}

					if ( $key ) {
						$key_prompt_count = strlen( $key_prompt ) - strlen( $value_token ) - 1;
						$value_prompt     = str_repeat( ' ', $key_prompt_count ) . '=' . $value_token;

						$value = $this->prompt( $value_prompt );
						if ( false === $value ) {
							return [ $args, $assoc_args ];
						}

						$assoc_args[ $key ] = $value;

						$repeat = true;
					} else {
						$repeat = false;
					}
				} while ( $repeat );

			} else {
				$token       = isset( $spec_arg['token'] ) && is_string( $spec_arg['token'] ) ? $spec_arg['token'] : '';
				$prompt      = $current_prompt . $token;
				$default_val = null;

				// Add description if available
				$longdesc    = $this->get_longdesc();
				$description = $this->get_arg_description( $spec_arg, $docparser, $longdesc );

				if ( ! empty( $description ) ) {
					$prompt .= ' (' . $description . ')';
				}

				// Get default value for the argument (not for flags)
				if ( 'flag' === $spec_arg['type'] ) {
					$prompt .= ' (Y/n)';
				} elseif ( 'positional' === $spec_arg['type'] || 'assoc' === $spec_arg['type'] ) {
					$spec_args = ( 'positional' === $spec_arg['type'] )
						? $docparser->get_arg_args( $spec_name )
						: $docparser->get_param_args( $spec_name );
					if ( null !== $spec_args && isset( $spec_args['default'] ) ) {
						$default_val = $spec_args['default'];
						$prompt     .= ' [' . ( is_scalar( $default_val ) ? (string) $default_val : '' ) . ']';
					}
				}

				$response = $this->prompt( $prompt );
				if ( false === $response ) {
					return [ $args, $assoc_args ];
				}

				// If response is empty and there's a default (not a flag), use the default
				if ( '' === $response && null !== $default_val ) {
					$response = $default_val;
				}

				if ( '' !== $response ) {
					$resp_str = is_string( $response ) ? $response : ( is_scalar( $response ) ? (string) $response : '' );
					switch ( $spec_arg['type'] ) {
						case 'positional':
							if ( $spec_arg['repeating'] ) {
								$response = explode( ' ', $resp_str );
							} else {
								$response = [ $resp_str ];
							}
							$args = array_merge( $args, $response );
							break;
						case 'assoc':
							$assoc_args[ $spec_name ] = $response;
							break;
						case 'flag':
							if ( 'Y' === strtoupper( $resp_str ) ) {
								$assoc_args[ $spec_name ] = true;
							}
							break;
					}
				}
			}
		}

		return [ $args, $assoc_args ];
	}

	/**
	 * Create a DocParser instance from the command's description.
	 *
	 * This creates a mock DocParser from the command's short and long descriptions,
	 * used internally for getting argument metadata.
	 *
	 * @return DocParser
	 */
	private function create_mock_docparser() {
		$mock_doc = [ $this->get_shortdesc(), '' ];
		$mock_doc = array_merge( $mock_doc, explode( "\n", $this->get_longdesc() ) );
		$mock_doc = '/**' . PHP_EOL . '* ' . implode( PHP_EOL . '* ', $mock_doc ) . PHP_EOL . '*/';
		return new DocParser( $mock_doc );
	}

	/**
	 * Resolve alias argument names to their canonical parameter names.
	 *
	 * @param array<string, mixed>  $assoc_args      Arguments passed to command.
	 * @param array<string, string> $aliases         Map of alias => canonical_name.
	 * @param array<string, bool>   $repeating_params Map of canonical_name => true for repeating params.
	 * @return array<string, mixed> Arguments with aliases resolved to canonical names.
	 */
	private function resolve_arg_aliases( $assoc_args, $aliases, $repeating_params = [] ) {
		if ( empty( $aliases ) ) {
			return $assoc_args;
		}

		// First pass: copy all non-alias entries to $resolved_args.
		$resolved_args = [];
		foreach ( $assoc_args as $key => $value ) {
			if ( ! isset( $aliases[ $key ] ) ) {
				$resolved_args[ $key ] = $value;
			}
		}

		// Second pass: resolve aliases.
		foreach ( $assoc_args as $key => $value ) {
			if ( ! isset( $aliases[ $key ] ) ) {
				continue;
			}

			$canonical_key = $aliases[ $key ];
			WP_CLI::debug( "Alias resolved: --{$key} => --{$canonical_key}", 'bootstrap' );

			if ( ! array_key_exists( $canonical_key, $resolved_args ) ) {
				// Canonical name not yet present; use alias value.
				$resolved_args[ $canonical_key ] = $value;
			} elseif ( ! empty( $repeating_params[ $canonical_key ] ) ) {
				// Canonical name present and parameter is repeating; merge values.
				$existing = $resolved_args[ $canonical_key ];
				if ( ! is_array( $existing ) ) {
					$existing = [ $existing ];
				}
				$alias_values                    = is_array( $value ) ? $value : [ $value ];
				$resolved_args[ $canonical_key ] = array_merge( $existing, $alias_values );
			} else {
				// Canonical name present and not repeating; canonical wins.
				WP_CLI::debug(
					sprintf(
						'Ignoring alias --%s because --%s was already provided.',
						$key,
						$canonical_key
					),
					'bootstrap'
				);
			}
		}

		return $resolved_args;
	}

	/**
	 * Validate the supplied arguments to the command.
	 * Throws warnings or errors if arguments are missing
	 * or invalid.
	 *
	 * @param array<mixed>         $args
	 * @param array<string, mixed> $assoc_args
	 * @param array<string, mixed> $extra_args
	 * @return array{0: array<string>, 1: array<mixed>, 2: array<string, mixed>, 3: array<string, mixed>} list of invalid $assoc_args keys to unset
	 */
	private function validate_args( $args, $assoc_args, $extra_args ) {
		$synopsis = $this->get_synopsis();
		if ( ! $synopsis ) {
			return [ [], $args, $assoc_args, $extra_args ];
		}

		$validator = new SynopsisValidator( $synopsis );

		$cmd_path = implode( ' ', get_path( $this ) );
		foreach ( $validator->get_unknown() as $token ) {
			\WP_CLI::warning(
				sprintf(
					'The `%s` command has an invalid synopsis part: %s',
					$cmd_path,
					$token
				)
			);
		}

		/** @var array<int, string> $positionals */
		$positionals = array_values(
			array_map(
				static function ( $val ) {
					return is_string( $val ) ? $val : ( is_scalar( $val ) ? (string) $val : '' );
				},
				$args
			)
		);

		if ( ! $validator->enough_positionals( $positionals ) ) {
			$this->show_usage();
			exit( 1 );
		}

		$unknown_positionals = $validator->unknown_positionals( $positionals );
		if ( ! empty( $unknown_positionals ) ) {
			\WP_CLI::error(
				'Too many positional arguments: ' .
				implode( ' ', $unknown_positionals )
			);
		}

		$synopsis_spec = SynopsisParser::parse( $synopsis );
		$i             = 0;
		$errors        = [
			'fatal'   => [],
			'warning' => [],
		];
		$docparser     = $this->create_mock_docparser();
		foreach ( $synopsis_spec as $spec ) {
			$spec_name = isset( $spec['name'] ) && is_string( $spec['name'] ) ? $spec['name'] : '';

			if ( 'positional' === $spec['type'] ) {
				$spec_args = $docparser->get_arg_args( $spec_name );
				if ( ! isset( $args[ $i ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$args[ $i ] = $spec_args['default'];
					}
				}
				if ( isset( $spec_args['options'] ) && is_array( $spec_args['options'] ) ) {
					$options = $spec_args['options'];
					if ( ! empty( $spec['repeating'] ) ) {
						do {
							// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
							if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $options ) ) {
								\WP_CLI::error( 'Invalid value specified for positional arg.' );
							}
							++$i;
						} while ( isset( $args[ $i ] ) );
					} elseif ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $options ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
						\WP_CLI::error( 'Invalid value specified for positional arg.' );
					}
				}
				++$i;
			} elseif ( 'assoc' === $spec['type'] ) {
				$spec_args = $docparser->get_param_args( $spec_name );

				// Handle repeating parameter (e.g., [--status=<status>...])
				if ( isset( $assoc_args[ $spec_name ] ) && is_array( $assoc_args[ $spec_name ] ) ) {
					// If repeating is not set, use only the last value
					if ( empty( $spec['repeating'] ) ) {
						$values       = $assoc_args[ $spec_name ];
						$values_count = count( $values );
						if ( $values_count > 0 ) {
							$assoc_args[ $spec_name ] = $values[ $values_count - 1 ];
						}
					}
				}

				if ( ! isset( $assoc_args[ $spec_name ] ) && ! isset( $extra_args[ $spec_name ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$assoc_args[ $spec_name ] = $spec_args['default'];
					}
				}
				if ( isset( $assoc_args[ $spec_name ] ) && isset( $spec_args['options'] ) && is_array( $spec_args['options'] ) ) {
					/**
					 * @var string|string[] $value
					 */
					$value   = $assoc_args[ $spec_name ];
					$options = $spec_args['options'];

					// Handle validation for multiple values
					if ( is_array( $value ) ) {
						foreach ( $value as $single_value ) {
							// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
							if ( ! in_array( $single_value, $options ) ) {
								$errors['fatal'][ $spec_name ] = "Invalid value '{$single_value}' specified for '{$spec_name}'";
								break;
							}
						}
					} elseif ( ! in_array( $value, $options ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
						// Try whether it might be a comma-separated list of multiple values.
						$values = array_map( 'trim', explode( ',', $value ) );
						$count  = count( $values );
						if (
							$count > 1
							&&
							count(
								array_filter(
									$values,
									static function ( $value ) use ( $options ) {
										return in_array( $value, $options, true );
									}
								)
							) === $count
						) {
							continue;
						}
						$errors['fatal'][ $spec_name ] = "Invalid value specified for '{$spec_name}'";
					}
				}
			}
		}

		$config                             = \WP_CLI::get_config();
		list( $returned_errors, $to_unset ) = $validator->validate_assoc(
			array_merge( $config, $extra_args, $assoc_args )
		);
		foreach ( [ 'fatal', 'warning' ] as $error_type ) {
			$errors[ $error_type ] = array_merge( $errors[ $error_type ], $returned_errors[ $error_type ] );
		}

		if ( 'help' !== $this->name ) {
			// A `--<field>=<value>` token means the command accepts keys that cannot be
			// enumerated up front, so an unrecognized parameter is reported only when it
			// looks like a typo of a documented one. Anything further away is passed
			// through untouched, which keeps custom fields and query filters working.
			$has_generic = $validator->has_generic();

			// Global parameters are left out of the candidate set for those commands. They
			// are harmless as a hint on an error raised anyway, but a catch-all command's
			// own arguments share their namespace: `wp post list --cat=5` and `--s=hello`
			// are real query vars two edits away from `--path` and `--ssh`.
			$parameters = $this->get_parameters( $synopsis_spec, ! $has_generic );

			foreach ( $validator->unknown_assoc( $assoc_args, $has_generic ) as $key ) {
				// The alias map in get_suggestion() is command vocabulary and ignores the
				// threshold. That is fine when a suggestion merely decorates an error we
				// already decided to raise, but here it would be the thing raising it.
				$suggestion = Utils\get_suggestion(
					$key,
					$parameters,
					2,
					! $has_generic
				);

				if ( $has_generic && '' === $suggestion ) {
					continue;
				}

				$errors['fatal'][] = sprintf(
					'unknown --%s parameter%s',
					$key,
					! empty( $suggestion ) ? PHP_EOL . "Did you mean '--{$suggestion}'?" : ''
				);
			}
		}

		if ( ! empty( $errors['fatal'] ) ) {
			$out = 'Parameter errors:';
			foreach ( $errors['fatal'] as $key => $error ) {
				$out .= "\n {$error}";
				$desc = $docparser->get_param_desc( (string) $key );
				if ( '' !== $desc ) {
					$out .= " ({$desc})";
				}
			}

			\WP_CLI::error( $out );
		}

		array_map( '\\WP_CLI::warning', $errors['warning'] );

		return [ $to_unset, $args, $assoc_args, $extra_args ];
	}

	/**
	 * Get the list of sensitive argument names from the synopsis.
	 * These arguments will have their values masked in log output.
	 *
	 * @return array<string> Array of argument names that are marked as sensitive
	 */
	private function get_sensitive_args() {
		$synopsis = $this->get_synopsis();
		if ( ! $synopsis ) {
			return [];
		}

		$synopsis_spec  = SynopsisParser::parse( $synopsis );
		$docparser      = $this->create_mock_docparser();
		$sensitive_args = [];

		foreach ( $synopsis_spec as $spec ) {
			if ( 'assoc' === $spec['type'] && isset( $spec['name'] ) && is_string( $spec['name'] ) ) {
				$spec_args = $docparser->get_param_args( $spec['name'] );
				if ( isset( $spec_args['sensitive'] ) && $spec_args['sensitive'] ) {
					$sensitive_args[] = $spec['name'];
				}
			}
		}

		return $sensitive_args;
	}

	/**
	 * Get deprecated assoc arguments from the synopsis.
	 *
	 * @return array<string,string> Deprecated argument names and their deprecation messages.
	 */
	private function get_deprecated_assoc_args() {
		$synopsis = $this->get_synopsis();
		if ( ! $synopsis ) {
			return [];
		}

		return DocParser::get_deprecated_assoc_args( $synopsis, $this->create_mock_docparser() );
	}

	/**
	 * Invoke the subcommand with the supplied arguments.
	 * Given a --prompt argument, interactively request input
	 * from the end user.
	 *
	 * @param array<mixed>         $args
	 * @param array<string, mixed> $assoc_args
	 * @param array<mixed>         $extra_args
	 * @return void
	 */
	public function invoke( $args, $assoc_args, $extra_args ) {
		static $prompted_once = false;

		// Build alias map from the parsed synopsis and resolve to canonical names.
		/** @var array<string, string> $aliases */
		$aliases          = [];
		$repeating_params = [];
		$synopsis_spec    = SynopsisParser::parse( $this->get_synopsis() );

		// Build a set of assoc/flag canonical names (local + global) for conflict detection.
		// Positional parameter names are excluded because an alias matching a positional
		// name would not cause any real ambiguity (--alias vs bare positional).
		/** @var list<string> $assoc_flag_names */
		$assoc_flag_names = [];
		foreach ( $synopsis_spec as $param ) {
			if ( in_array( $param['type'], [ 'assoc', 'flag' ], true ) && isset( $param['name'] ) && is_string( $param['name'] ) ) {
				$assoc_flag_names[] = $param['name'];
			}
			if ( 'assoc' === $param['type'] && ! empty( $param['repeating'] ) && isset( $param['name'] ) && is_string( $param['name'] ) ) {
				$repeating_params[ $param['name'] ] = true;
			}
		}
		foreach ( SynopsisParser::parse( $this->get_global_params() ) as $param ) {
			if ( in_array( $param['type'], [ 'assoc', 'flag' ], true ) && isset( $param['name'] ) && is_string( $param['name'] ) ) {
				$assoc_flag_names[] = $param['name'];
			}
		}
		$assoc_flag_names = array_values( array_unique( $assoc_flag_names ) );

		foreach ( $synopsis_spec as $param ) {
			if ( empty( $param['aliases'] ) || ! is_array( $param['aliases'] ) || ! isset( $param['name'] ) || ! is_string( $param['name'] ) ) {
				continue;
			}

			$param_name = $param['name'];
			foreach ( $param['aliases'] as $alias ) {
				$alias = is_string( $alias ) ? $alias : ( is_scalar( $alias ) ? (string) $alias : '' );
				// Detect duplicate aliases (same alias used for different params).
				if ( isset( $aliases[ $alias ] ) && $aliases[ $alias ] !== $param_name ) {
					WP_CLI::warning(
						sprintf(
							"Alias '%s' for parameter '%s' conflicts with existing alias for parameter '%s'. Skipping.",
							$alias,
							$param_name,
							$aliases[ $alias ]
						)
					);
					continue;
				}

				// Detect aliases that conflict with an assoc/flag canonical parameter name.
				if ( in_array( $alias, $assoc_flag_names, true ) && $alias !== $param_name ) {
					WP_CLI::warning(
						sprintf(
							"Alias '%s' for parameter '%s' conflicts with an existing parameter name. Skipping.",
							$alias,
							$param_name
						)
					);
					continue;
				}

				$aliases[ $alias ] = $param_name;
			}
		}
		if ( ! empty( $aliases ) ) {
			WP_CLI::debug( 'Resolving argument aliases: ' . implode( ', ', array_keys( $aliases ) ), 'bootstrap' );
		}
		$assoc_args = $this->resolve_arg_aliases( $assoc_args, $aliases, $repeating_params );
		$extra_args = $this->resolve_arg_aliases( $extra_args, $aliases, $repeating_params );

		if ( 'help' !== $this->name ) {
			if ( \WP_CLI::get_config( 'prompt' ) && ! $prompted_once ) {
				list( $_args, $assoc_args ) = $this->prompt_args( $args, $assoc_args );
				$args                       = array_merge( $args, $_args );
				$prompted_once              = true;
			}
		}

		$extra_positionals = [];
		foreach ( $extra_args as $k => $v ) {
			if ( is_numeric( $k ) ) {
				if ( ! isset( $args[ $k ] ) ) {
					$extra_positionals[ $k ] = $v;
				}
				unset( $extra_args[ $k ] );
			}
		}
		$args += $extra_positionals;

		$provided_assoc_arg_names = [];
		foreach ( [ $assoc_args, $extra_args ] as $assoc_arg_set ) {
			foreach ( $assoc_arg_set as $arg_name => $value ) {
				if ( ! is_numeric( $arg_name ) ) {
					$provided_assoc_arg_names[ $arg_name ] = true;
				}
			}
		}

		list( $to_unset, $args, $assoc_args, $extra_args ) = $this->validate_args( $args, $assoc_args, $extra_args );

		foreach ( $to_unset as $key ) {
			unset( $assoc_args[ $key ] );
		}

		$parent_cmd = $this->get_parent();
		$path       = $parent_cmd ? get_path( $parent_cmd ) : [];
		$parent     = implode( ' ', array_slice( $path, 1 ) );
		$cmd        = $this->name;
		if ( $parent ) {
			/**
			 * Action triggered before a parent command is invoked.
			 *
			 * @param string $parent Parent command name.
			 */
			WP_CLI::do_hook( "before_invoke:{$parent}", $parent );
			$cmd = $parent . ' ' . $cmd;
		}

		/**
		 * Action triggered before a command is invoked.
		 *
		 * @param string $cmd Command name.
		 */
		WP_CLI::do_hook( "before_invoke:{$cmd}", $cmd );

		$docparser = $this->get_docparser();
		if ( $docparser && $docparser->has_tag( 'deprecated' ) ) {
			$deprecation_message = $docparser->get_deprecation_message();
			$warning             = sprintf( 'The `%s` command is deprecated.', $cmd );
			if ( '' !== $deprecation_message ) {
				$warning .= ' ' . $deprecation_message;
			}
			WP_CLI::warning( $warning );
		}

		foreach ( $this->get_deprecated_assoc_args() as $arg_name => $deprecation_message ) {
			if ( ! isset( $provided_assoc_arg_names[ $arg_name ] ) ) {
				continue;
			}

			$warning = sprintf( 'The `--%s` argument for `%s` is deprecated.', $arg_name, $cmd );
			if ( '' !== $deprecation_message ) {
				$warning .= ' ' . $deprecation_message;
			}

			WP_CLI::warning( $warning );
		}

		// Check if `--prompt` arg passed or not.
		if ( $prompted_once ) {
			// Unset empty args.
			$actual_args = $assoc_args;
			foreach ( $actual_args as $key => $value ) {
				if ( empty( $value ) ) {
					unset( $actual_args[ $key ] );
				}
			}

			// Get list of sensitive arguments to mask in output
			$sensitive_args = $this->get_sensitive_args();

			/** @var array<bool|float|int|string|null> $args_str */
			$args_str = $args;

			WP_CLI::log(
				sprintf(
					'wp %s %s',
					$cmd,
					ltrim(
						implode(
							' ',
							[
								ltrim( Utils\args_to_str( $args_str ), ' ' ),
								ltrim( Utils\assoc_args_to_str( $actual_args, $sensitive_args ), ' ' ),
							]
						),
						' '
					)
				)
			);
		}

		call_user_func( $this->when_invoked, $args, array_merge( $extra_args, $assoc_args ) );

		if ( $parent ) {
			/**
			 * Action triggered after a parent command has been invoked.
			 *
			 * @param string $parent Parent command name.
			 */
			WP_CLI::do_hook( "after_invoke:{$parent}", $parent );
		}

		/**
		 * Action triggered after a command has been invoked.
		 *
		 * @param string $cmd Command name.
		 */
		WP_CLI::do_hook( "after_invoke:{$cmd}", $cmd );
	}

	/**
	 * Get an array of parameter names, by merging the command-specific and the
	 * global parameters.
	 *
	 * @param array<int, array<string, mixed>> $spec           Optional. Specification of the current command.
	 * @param bool                             $include_global Optional. Whether to include the global parameters.
	 *
	 * @return array<int, string> Array of parameter names
	 */
	private function get_parameters( $spec = [], $include_global = true ) {
		/** @var list<string> $local_parameters */
		$local_parameters = array_values( array_filter( array_column( $spec, 'name' ), 'is_string' ) );

		if ( ! $include_global ) {
			return array_values( array_unique( $local_parameters ) );
		}

		/** @var list<string> $global_parameters */
		$global_parameters = array_values(
			array_filter(
				array_column(
					SynopsisParser::parse( $this->get_global_params() ),
					'name'
				),
				'is_string'
			)
		);

		return array_values( array_unique( array_merge( $local_parameters, $global_parameters ) ) );
	}
}
