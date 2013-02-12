<?php

namespace WP_CLI\Dispatcher;

class CompositeCommand extends AbstractCommandContainer implements Documentable {

	protected $name;
	protected $shortdesc;

	public function __construct( $name, $shortdesc ) {
		$this->name = $name;
		$this->shortdesc = $shortdesc;
	}

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return \WP_CLI::$root;
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

	public function get_full_synopsis() {
		$str = array();

		foreach ( $this->subcommands as $subcommand ) {
			$str[] = $subcommand->get_full_synopsis( true );
		}

		return implode( "\n\n", $str );
	}
}

