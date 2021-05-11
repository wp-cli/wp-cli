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

	private $alias;

	private $when_invoked;

	public function __construct( $parent, $name, $docparser, $when_invoked ) {
		parent::__construct( $parent, $name, $docparser );

		$this->when_invoked = $when_invoked;

		$this->alias = $docparser->get_tag( 'alias' );

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
	 * @param string $default
	 * @return string|false
	 */
	private function prompt( $question, $default ) {

		$question .= ': ';
		if ( function_exists( 'readline' ) ) {
			return readline( $question );
		}

		echo $question;

		$ret = stream_get_line( STDIN, 1024, "\n" );
		if ( Utils\is_windows() && "\r" === substr( $ret, -1 ) ) {
			$ret = substr( $ret, 0, -1 );
		}
		return $ret;
	}

	/**
	 * Interactively prompt the user for input
	 * based on defined synopsis and passed arguments.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return array
	 */
	private function prompt_args( $args, $assoc_args ) {

		$synopsis = $this->get_synopsis();

		if ( ! $synopsis ) {
			return [ $args, $assoc_args ];
		}

		// To skip the already provided positional arguments, we need to count
		// how many we had already received.
		$arg_index = 0;

		$spec = array_filter(
			SynopsisParser::parse( $synopsis ),
			function( $spec_arg ) use ( $args, $assoc_args, &$arg_index ) {
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
						return ! isset( $assoc_args[ $spec_arg['name'] ] );
				}
			}
		);

		$spec = array_values( $spec );

		$prompt_args = WP_CLI::get_config( 'prompt' );
		if ( true !== $prompt_args ) {
			$prompt_args = explode( ',', $prompt_args );
		}

		// 'positional' arguments are positional (aka zero-indexed)
		// so $args needs to be reset before prompting for new arguments
		$args = [];

		foreach ( $spec as $key => $spec_arg ) {

			// When prompting for specific arguments (e.g. --prompt=user_pass),
			// ignore all arguments that don't match.
			if ( is_array( $prompt_args ) ) {
				if ( 'assoc' !== $spec_arg['type'] ) {
					continue;
				}
				if ( ! in_array( $spec_arg['name'], $prompt_args, true ) ) {
					continue;
				}
			}

			$current_prompt = ( $key + 1 ) . '/' . count( $spec ) . ' ';
			$default        = $spec_arg['optional'] ? '' : false;

			// 'generic' permits arbitrary key=value (e.g. [--<field>=<value>] )
			if ( 'generic' === $spec_arg['type'] ) {

				list( $key_token, $value_token ) = explode( '=', $spec_arg['token'] );

				$repeat = false;
				do {
					if ( ! $repeat ) {
						$key_prompt = $current_prompt . $key_token;
					} else {
						$key_prompt = str_repeat( ' ', strlen( $current_prompt ) ) . $key_token;
					}

					$key = $this->prompt( $key_prompt, $default );
					if ( false === $key ) {
						return [ $args, $assoc_args ];
					}

					if ( $key ) {
						$key_prompt_count = strlen( $key_prompt ) - strlen( $value_token ) - 1;
						$value_prompt     = str_repeat( ' ', $key_prompt_count ) . '=' . $value_token;

						$value = $this->prompt( $value_prompt, $default );
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
				$prompt = $current_prompt . $spec_arg['token'];
				if ( 'flag' === $spec_arg['type'] ) {
					$prompt .= ' (Y/n)';
				}

				$response = $this->prompt( $prompt, $default );
				if ( false === $response ) {
					return [ $args, $assoc_args ];
				}

				if ( $response ) {
					switch ( $spec_arg['type'] ) {
						case 'positional':
							if ( $spec_arg['repeating'] ) {
								$response = explode( ' ', $response );
							} else {
								$response = [ $response ];
							}
							$args = array_merge( $args, $response );
							break;
						case 'assoc':
							$assoc_args[ $spec_arg['name'] ] = $response;
							break;
						case 'flag':
							if ( 'Y' === strtoupper( $response ) ) {
								$assoc_args[ $spec_arg['name'] ] = true;
							}
							break;
					}
				}
			}
		}

		return [ $args, $assoc_args ];
	}

	/**
	 * Validate the supplied arguments to the command.
	 * Throws warnings or errors if arguments are missing
	 * or invalid.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param array $extra_args
	 * @return array list of invalid $assoc_args keys to unset
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

		if ( ! $validator->enough_positionals( $args ) ) {
			$this->show_usage();
			exit( 1 );
		}

		$unknown_positionals = $validator->unknown_positionals( $args );
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
		$mock_doc      = [ $this->get_shortdesc(), '' ];
		$mock_doc      = array_merge( $mock_doc, explode( "\n", $this->get_longdesc() ) );
		$mock_doc      = '/**' . PHP_EOL . '* ' . implode( PHP_EOL . '* ', $mock_doc ) . PHP_EOL . '*/';
		$docparser     = new DocParser( $mock_doc );
		foreach ( $synopsis_spec as $spec ) {
			if ( 'positional' === $spec['type'] ) {
				$spec_args = $docparser->get_arg_args( $spec['name'] );
				if ( ! isset( $args[ $i ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$args[ $i ] = $spec_args['default'];
					}
				}
				if ( isset( $spec_args['options'] ) ) {
					if ( ! empty( $spec['repeating'] ) ) {
						do {
							// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
							if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $spec_args['options'] ) ) {
								\WP_CLI::error( 'Invalid value specified for positional arg.' );
							}
							$i++;
						} while ( isset( $args[ $i ] ) );
					} else {
						// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
						if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $spec_args['options'] ) ) {
							\WP_CLI::error( 'Invalid value specified for positional arg.' );
						}
					}
				}
				$i++;
			} elseif ( 'assoc' === $spec['type'] ) {
				$spec_args = $docparser->get_param_args( $spec['name'] );
				if ( ! isset( $assoc_args[ $spec['name'] ] ) && ! isset( $extra_args[ $spec['name'] ] ) ) {
					if ( isset( $spec_args['default'] ) ) {
						$assoc_args[ $spec['name'] ] = $spec_args['default'];
					}
				}
				if ( isset( $assoc_args[ $spec['name'] ] ) && isset( $spec_args['options'] ) ) {
					$value   = $assoc_args[ $spec['name'] ];
					$options = $spec_args['options'];
					// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- This is a loose comparison by design.
					if ( ! in_array( $value, $options ) ) {
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
						$errors['fatal'][ $spec['name'] ] = "Invalid value specified for '{$spec['name']}'";
					}
				}
			}
		}

		list( $returned_errors, $to_unset ) = $validator->validate_assoc(
			array_merge( \WP_CLI::get_config(), $extra_args, $assoc_args )
		);
		foreach ( [ 'fatal', 'warning' ] as $error_type ) {
			$errors[ $error_type ] = array_merge( $errors[ $error_type ], $returned_errors[ $error_type ] );
		}

		if ( 'help' !== $this->name ) {
			foreach ( $validator->unknown_assoc( $assoc_args ) as $key ) {
				$suggestion    = Utils\get_suggestion(
					$key,
					$this->get_parameters( $synopsis_spec ),
					$threshold = 2
				);

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
				$desc = $docparser->get_param_desc( $key );
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
	 * Invoke the subcommand with the supplied arguments.
	 * Given a --prompt argument, interactively request input
	 * from the end user.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function invoke( $args, $assoc_args, $extra_args ) {
		static $prompted_once = false;

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

		list( $to_unset, $args, $assoc_args, $extra_args ) = $this->validate_args( $args, $assoc_args, $extra_args );

		foreach ( $to_unset as $key ) {
			unset( $assoc_args[ $key ] );
		}

		$path   = get_path( $this->get_parent() );
		$parent = implode( ' ', array_slice( $path, 1 ) );
		$cmd    = $this->name;
		if ( $parent ) {
			WP_CLI::do_hook( "before_invoke:{$parent}" );
			$cmd = $parent . ' ' . $cmd;
		}
		WP_CLI::do_hook( "before_invoke:{$cmd}" );

		// Check if `--prompt` arg passed or not.
		if ( $prompted_once ) {
			// Unset empty args.
			$actual_args = $assoc_args;
			foreach ( $actual_args as $key ) {
				if ( empty( $actual_args[ $key ] ) ) {
					unset( $actual_args[ $key ] );
				}
			}

			WP_CLI::log(
				sprintf(
					'wp %s %s',
					$cmd,
					ltrim( Utils\assoc_args_to_str( $actual_args ), ' ' )
				)
			);
		}

		call_user_func( $this->when_invoked, $args, array_merge( $extra_args, $assoc_args ) );

		if ( $parent ) {
			WP_CLI::do_hook( "after_invoke:{$parent}" );
		}
		WP_CLI::do_hook( "after_invoke:{$cmd}" );
	}

	/**
	 * Get an array of parameter names, by merging the command-specific and the
	 * global parameters.
	 *
	 * @param array  $spec Optional. Specification of the current command.
	 *
	 * @return array Array of parameter names
	 */
	private function get_parameters( $spec = [] ) {
		$local_parameters  = array_column( $spec, 'name' );
		$global_parameters = array_column(
			SynopsisParser::parse( $this->get_global_params() ),
			'name'
		);

		return array_unique( array_merge( $local_parameters, $global_parameters ) );
	}
}
