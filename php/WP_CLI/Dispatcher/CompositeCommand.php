<?php

namespace WP_CLI\Dispatcher;

/**
 * A non-leaf node in the command tree.
 */
class CompositeCommand {

	protected $name, $shortdesc, $synopsis;

	protected $parent, $subcommands = array();

	public function __construct( $parent, $name, $docparser ) {
		$this->parent = $parent;

		$this->name = $name;

		$this->shortdesc = $docparser->get_shortdesc();
		$this->longdesc = $docparser->get_longdesc();

		$when_to_invoke = $docparser->get_tag( 'when' );
		if ( $when_to_invoke ) {
			\WP_CLI::get_runner()->register_early_invoke( $when_to_invoke, $this );
		}
	}

	function get_parent() {
		return $this->parent;
	}

	function add_subcommand( $name, $command ) {
		$this->subcommands[ $name ] = $command;
	}

	function can_have_subcommands() {
		return true;
	}

	function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}

	function get_name() {
		return $this->name;
	}

	function get_shortdesc() {
		return $this->shortdesc;
	}

	function get_longdesc() {
		return $this->longdesc;
	}

	function get_synopsis() {
		return '<command>';
	}

	function get_usage( $prefix ) {
		return sprintf( "%s%s %s",
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		);
	}

	function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;

		foreach ( $methods as $name => $subcommand ) {
			$prefix = ( 0 == $i++ ) ? 'usage: ' : '   or: ';

			\WP_CLI::line( $subcommand->get_usage( $prefix ) );
		}

		\WP_CLI::line();
		\WP_CLI::line( "See 'wp help $this->name <command>' for more information on a specific command." );
	}

	function invoke( $args, $assoc_args, $extra_args ) {
		$this->show_usage();
	}

	function find_subcommand( &$args ) {
		$name = array_shift( $args );

		$subcommands = $this->get_subcommands();

		if ( !isset( $subcommands[ $name ] ) ) {
			$aliases = self::get_aliases( $subcommands );

			if ( isset( $aliases[ $name ] ) ) {
				$name = $aliases[ $name ];
			}
		}

		if ( !isset( $subcommands[ $name ] ) )
			return false;

		return $subcommands[ $name ];
	}

	private static function get_aliases( $subcommands ) {
		$aliases = array();

		foreach ( $subcommands as $name => $subcommand ) {
			$alias = $subcommand->get_alias();
			if ( $alias )
				$aliases[ $alias ] = $name;
		}

		return $aliases;
	}

	function get_alias() {
		return false;
	}
}

