<?php

namespace WP_CLI;

use WP_CLI;

class Completions {

	private $cur_word;
	private $words;
	private $opts = [];

	/**
	 * Instantiate a Completions object.
	 *
	 * @param string $line Line of shell input to compute a completion for.
	 */
	public function __construct( $line ) {
		// TODO: properly parse single and double quotes
		$this->words = explode( ' ', $line );

		// First word is always `wp`.
		array_shift( $this->words );

		// Last word is either empty or an incomplete subcommand.
		$this->cur_word = (string) end( $this->words );
		if ( '' !== $this->cur_word && ! preg_match( '/^\-/', $this->cur_word ) ) {
			array_pop( $this->words );
		}

		$is_alias = false;
		$is_help  = false;
		if ( ! empty( $this->words[0] ) && preg_match( '/^@/', $this->words[0] ) ) {
			array_shift( $this->words );
			// `wp @al` is false, but `wp @all ` is true.
			if ( count( $this->words ) ) {
				$is_alias = true;
			}
		} elseif ( ! empty( $this->words[0] ) && 'help' === $this->words[0] ) {
			array_shift( $this->words );
			$is_help = true;
		}

		$r = $this->get_command( $this->words );
		if ( ! is_array( $r ) ) {
			return;
		}

		list( $command, $args, $assoc_args ) = $r;

		$spec = SynopsisParser::parse( $command->get_synopsis() );

		foreach ( $spec as $arg ) {
			if ( 'positional' === $arg['type'] && 'file' === $arg['name'] ) {
				$this->add( '<file> ' );
				return;
			}
		}

		// Check if we're trying to complete a flag value (e.g., --format=<cursor>)
		if ( preg_match( '/^--([a-z-_0-9]+)=(.*)$/i', $this->cur_word, $matches ) ) {
			$param_name  = $matches[1];
			$param_value = $matches[2];
			$this->add_param_values( $command, $param_name, $param_value );
			return;
		}

		if ( $command->can_have_subcommands() ) {
			// Add completion when command is `wp` and alias isn't set.
			if ( 'wp' === $command->get_name() && false === $is_alias && false === $is_help ) {
				$aliases = WP_CLI::get_configurator()->get_aliases();
				foreach ( $aliases as $name => $_ ) {
					$this->add( "$name " );
				}
			}
			foreach ( $command->get_subcommands() as $name => $_ ) {
				$this->add( "$name " );
			}
		} else {
			foreach ( $spec as $arg ) {
				if ( in_array( $arg['type'], [ 'flag', 'assoc' ], true ) ) {
					if ( isset( $assoc_args[ $arg['name'] ] ) ) {
						continue;
					}

					$opt = "--{$arg['name']}";

					if ( 'flag' === $arg['type'] ) {
						$opt .= ' ';
					} elseif ( ! $arg['value']['optional'] ) {
						$opt .= '=';
					}

					$this->add( $opt );
				}
			}

			foreach ( $this->get_global_parameters() as $param => $runtime ) {
				if ( isset( $assoc_args[ $param ] ) ) {
					continue;
				}

				$opt = "--{$param}";

				if ( '' === $runtime || ! is_string( $runtime ) ) {
					$opt .= ' ';
				} else {
					$opt .= '=';
				}

				$this->add( $opt );
			}
		}
	}

	/**
	 * Get the specific WP-CLI command that is being referenced.
	 *
	 * @param array $words Individual input line words.
	 *
	 * @return array|mixed Array with command and arguments, or error result if command detection failed.
	 */
	private function get_command( $words ) {
		$positional_args = [];
		$assoc_args      = [];

		# Avoid having to polyfill array_key_last().
		end( $words );
		$last_arg_i = key( $words );
		foreach ( $words as $i => $arg ) {
			if ( preg_match( '|^--([^=]+)(=?)|', $arg, $matches ) ) {
				if ( $i === $last_arg_i && '' === $matches[2] ) {
					continue;
				}
				$assoc_args[ $matches[1] ] = true;
			} else {
				$positional_args[] = $arg;
			}
		}

		$r = WP_CLI::get_runner()->find_command_to_run( $positional_args );
		if ( ! is_array( $r ) && array_pop( $positional_args ) === $this->cur_word ) {
			$r = WP_CLI::get_runner()->find_command_to_run( $positional_args );
		}

		if ( ! is_array( $r ) ) {
			return $r;
		}

		list( $command, $args ) = $r;

		return [ $command, $args, $assoc_args ];
	}

	/**
	 * Get global parameters.
	 *
	 * @return array Associative array of global parameters.
	 */
	private function get_global_parameters() {
		$params = [];
		foreach ( WP_CLI::get_configurator()->get_spec() as $key => $details ) {
			if ( false === $details['runtime'] ) {
				continue;
			}

			if ( isset( $details['deprecated'] ) ) {
				continue;
			}

			if ( isset( $details['hidden'] ) ) {
				continue;
			}
			$params[ $key ] = $details['runtime'];

			// Add additional option like `--[no-]color`.
			if ( true === $details['runtime'] ) {
				$params[ 'no-' . $key ] = '';
			}
		}

		return $params;
	}

	/**
	 * Add parameter values to completions if the parameter has defined options.
	 *
	 * @param mixed  $command Command object.
	 * @param string $param_name Parameter name.
	 * @param string $param_value Current partial value.
	 */
	private function add_param_values( $command, $param_name, $param_value ) {
		$options = [];

		// First, try to get options from the command's documentation
		$longdesc = $command->get_longdesc();
		if ( $longdesc ) {
			$parser     = new DocParser( $longdesc );
			$param_args = $parser->get_param_args( $param_name );

			if ( $param_args && isset( $param_args['options'] ) ) {
				$options = $param_args['options'];
			}
		}

		// If no options found in command doc, check global parameters
		if ( empty( $options ) ) {
			$global_params = WP_CLI::get_configurator()->get_spec();
			if ( isset( $global_params[ $param_name ]['enum'] ) ) {
				$options = $global_params[ $param_name ]['enum'];
			}
		}

		if ( empty( $options ) ) {
			return;
		}

		// Add each option as a completion
		foreach ( $options as $option ) {
			// Check if the option matches the current partial value
			if ( '' === $param_value || 0 === strpos( (string) $option, $param_value ) ) {
				$this->opts[] = $option . ' ';
			}
		}
	}

	/**
	 * Store individual option.
	 *
	 * @param string $opt Option to store.
	 */
	private function add( $opt ): void {
		if ( '' !== $this->cur_word ) {
			if ( 0 !== strpos( $opt, $this->cur_word ) ) {
				return;
			}
		}

		$this->opts[] = $opt;
	}

	/**
	 * Render the stored options.
	 *
	 * @return void
	 */
	public function render() {
		foreach ( $this->opts as $opt ) {
			WP_CLI::line( $opt );
		}
	}
}
