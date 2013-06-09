<?php

namespace WP_CLI\Dispatcher;

/**
 * A non-leaf node in the command tree.
 */
class CompositeCommand {

	protected $name;
	protected $shortdesc;
	protected $subcommands = array();

	public function __construct( $name, $shortdesc ) {
		$this->name = $name;
		$this->shortdesc = $shortdesc;
	}

	function add_subcommand( $name, $command ) {
		$this->subcommands[ $name ] = $command;
	}

	function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return \WP_CLI::$root;
	}

	function get_synopsis() {
		return '';
	}

	function invoke( $args, $assoc_args ) {
		$this->show_usage();
	}

	function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;

		foreach ( $methods as $name => $subcommand ) {
			$prefix = ( 0 == $i++ ) ? 'usage: ' : '   or: ';

			$subcommand->show_usage( $prefix );
		}

		\WP_CLI::line();
		\WP_CLI::line( "See 'wp help $this->name <subcommand>' for more information on a specific subcommand." );
	}

	function pre_invoke( &$args ) {
		return $this->find_subcommand( $args );
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

	public function get_shortdesc() {
		return $this->shortdesc;
	}
}

