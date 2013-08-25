<?php

namespace WP_CLI\Dispatcher;

/**
 * A leaf node in the command tree.
 */
class Subcommand extends CompositeCommand {

	private $alias;

	private $when_invoked;

	private $prompt = false;

	function __construct( $parent, $name, $docparser, $when_invoked ) {
		$this->when_invoked = $when_invoked;

		$this->synopsis = $docparser->get_synopsis();
		$this->alias = $docparser->get_tag( 'alias' );

		parent::__construct( $parent, $name, $docparser );
	}

	function get_synopsis() {
		return $this->synopsis;
	}

	function get_alias() {
		return $this->alias;
	}

	function set_prompt( $value ) {
		$this->prompt = (bool)$value;
	}

	function get_prompt() {
		return $this->prompt;
	}

	function show_usage( $prefix = 'usage: ' ) {
		\WP_CLI::line( sprintf( "%s%s %s",
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		) );
	}

	private function prompt_args( $args, $assoc_args ) {

		$synopsis = $this->get_synopsis();

		if ( ! $synopsis )
			return array( $args, $assoc_args );

		$spec = array_filter( \WP_CLI\SynopsisParser::parse( $synopsis ), function( $spec_arg ) {
			return in_array( $spec_arg['type'], array( 'generic', 'positional', 'assoc', 'flag' ) );
		});

		$spec = array_values( $spec );

		// 'positional' arguments are positional (aka zero-indexed)
		// so $args needs to be reset before prompting for new arguments
		$args = array();
		foreach( $spec as $key => $spec_arg ) {

			$current_prompt = ( $key + 1 ) . '/' . count( $spec ) . ' ';
			$required = ! $spec_arg['optional'];

			// 'generic' permits arbitrary key=value (e.g. [--<field>=<value>] )
			if ( 'generic' == $spec_arg['type'] ) {

				list( $key_token, $value_token ) = explode( '=', $spec_arg['token'] );

				$repeat = false;
				do {
					if ( ! $repeat )
						$key_prompt = $current_prompt . $key_token;
					else
						$key_prompt = str_repeat( " ", strlen( $current_prompt ) ) . $key_token;

					$key = \WP_CLI::prompt( $key_prompt, $required );
					if ( $key ) {
						$key_prompt_count = strlen( $key_prompt ) - strlen( $value_token ) - 1;
						$value_prompt = str_repeat( " ", $key_prompt_count ) . '=' . $value_token;
						$value = \WP_CLI::prompt( $value_prompt );
						$assoc_args[$key] = $value;

						$repeat = true;
						$required = false;
					} else {
						$repeat = false;
					}

				} while( $required || $repeat );

			} else {

				$prompt = $current_prompt . $spec_arg['token'];
				if ( 'flag' == $spec_arg['type'] )
					$prompt .= ' (Y/n)';

				$response = \WP_CLI::prompt( $prompt, $required );
				if ( $response ) {
					switch ( $spec_arg['type'] ) {
						case 'positional':
							if ( $spec_arg['repeating'] )
								$response = explode( ' ', $response );
							else
								$response = array( $response );
							$args = array_merge( $args, $response );
							break;
						case 'assoc':
							$assoc_args[$spec_arg['name']] = $response;
							break;
						case 'flag':
							if ( 'Y' == $response )
								$assoc_args[$spec_arg['name']] = true;
							break;
					}
				}
			}
		}

		return array( $args, $assoc_args );
	}

	private function validate_args( $args, &$assoc_args ) {
		$synopsis = $this->get_synopsis();

		if ( !$synopsis )
			return;

		$parser = new \WP_CLI\SynopsisValidator( $synopsis );

		$cmd_path = implode( ' ', get_path( $this ) );
		foreach ( $parser->get_unknown() as $token ) {
			\WP_CLI::warning( sprintf(
				"The `%s` command has an invalid synopsis part: %s",
				$cmd_path, $token
			) );
		}

		if ( !$parser->enough_positionals( $args ) ) {
			$this->show_usage();
			exit(1);
		}

		$errors = $parser->validate_assoc( $assoc_args, array_keys( \WP_CLI::get_config() ) );

		if ( !empty( $errors['fatal'] ) ) {
			$out = '';
			foreach ( $errors['fatal'] as $error ) {
				$out .= "\n " . $error;
			}

			\WP_CLI::error( $out, "Parameter errors" );
		}

		array_map( '\\WP_CLI::warning', $errors['warning'] );

		foreach ( $parser->unknown_assoc( $assoc_args ) as $key ) {
			\WP_CLI::warning( "unknown --$key parameter" );
		}
	}

	function invoke( $args, $assoc_args ) {

		if ( $this->get_prompt() )
			list( $args, $assoc_args ) = $this->prompt_args( $args, $assoc_args );

		$this->validate_args( $args, $assoc_args );

		\WP_CLI::do_action( 'before_invoke:' . $this->get_parent()->get_name() );

		call_user_func( $this->when_invoked, $args, $assoc_args );
	}
}

