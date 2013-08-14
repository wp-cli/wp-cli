<?php

namespace WP_CLI\Dispatcher;

/**
 * A leaf node in the command tree.
 */
class Subcommand extends CompositeCommand {

	private $alias;

	private $when_invoked;

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

	function show_usage( $prefix = 'usage: ' ) {
		\WP_CLI::line( sprintf( "%s%s %s",
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		) );
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
		$this->validate_args( $args, $assoc_args );

		\WP_CLI::do_action( 'before_invoke:' . $this->get_parent()->get_name() );

		call_user_func( $this->when_invoked, $args, $assoc_args );
	}
}

