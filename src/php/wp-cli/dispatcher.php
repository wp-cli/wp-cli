<?php

namespace WP_CLI\Dispatcher;

interface Command {

	function autocomplete();
	function shortdesc();
	function show_usage();
	function invoke( $arguments, $assoc_args );
}


class CompositeCommand implements Command {

	function __construct( $name, $class ) {
		$this->name = $name;
		$this->class = $class;
	}

	function autocomplete() {
		$subcommands = array_keys( $this->get_subcommands() );
		return $this->name .  ' ' . implode( ' ', $subcommands );
	}

	function shortdesc() {
		$methods = array_keys( $this->get_subcommands() );

		return implode( '|', $methods );
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

	function invoke( $args, $assoc_args ) {
		$subcommand = $this->find_subcommand( $args );

		if ( !$subcommand ) {
			$this->show_usage();
			return;
		}

		$subcommand->invoke( $args, $assoc_args );
	}

	protected function find_subcommand( &$args ) {
		$class = $this->class;

		if ( empty( $args ) ) {
			$name = $class::get_default_subcommand();
		} else {
			$name = array_shift( $args );
		}

		$aliases = $class::get_aliases();

		if ( isset( $aliases[ $name ] ) ) {
			$name = $aliases[ $name ];
		}

		$subcommands = $this->get_subcommands();

		if ( !isset( $subcommands[ $name ] ) )
			return false;

		return $subcommands[ $name ];
	}

	private function get_subcommands() {
		$reflection = new \ReflectionClass( $this->class );

		$subcommands = array();

		foreach ( $reflection->getMethods() as $method ) {
			if ( !self::_is_good_method( $method ) )
				continue;

			$subcommand = new MethodSubcommand( $method, $this );

			$subcommands[ $subcommand->get_name() ] = $subcommand;
		}

		return $subcommands;
	}

	private static function _is_good_method( $method ) {
		return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
	}
}


class SingleCommand extends AbstractSubcommand implements Command {

	function __construct( $name, $callable ) {
		$this->name = $name;
		$this->callable = $callable;

		$method = new \ReflectionMethod( $this->callable, '__invoke' );

		parent::__construct( $method );
	}

	function autocomplete() {
		return $this->name;
	}

	function shortdesc() {
		return '';
	}

	function show_usage( $prefix = 'usage: ' ) {
		$command = $this->name;
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $command $synopsis" );
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$this->method->invoke( $this->callable, $args, $assoc_args );
	}
}

