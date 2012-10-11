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

		$class = $this->class;
		$instance = new $class;

		$subcommand->invoke( $instance, $args, $assoc_args );
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

			$subcommand = new Subcommand( $method, $this->name );

			$subcommands[ $subcommand->get_name() ] = $subcommand;
		}

		return $subcommands;
	}

	private static function _is_good_method( $method ) {
		return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
	}
}


class SimpleCommand extends CompositeCommand {

	function __construct( $name, $callable ) {
		$this->name = $name;
		$this->callable = $callable;
	}

	function autocomplete() {
		return $this->name;
	}

	function shortdesc() {
		return '';
	}

	function show_usage() {
		\WP_CLI::line(  "usage: wp $this->name" );
	}

	function invoke( $args, $assoc_args ) {
		$subcommand = $this->find_subcommand( $args );

		$subcommand->invoke( $this->callable, $args, $assoc_args );
	}

	protected function find_subcommand( &$args ) {
		$method = new \ReflectionMethod( $this->callable, '__invoke' );

		return new SimpleSubcommand( $method, $this->name );
	}
}


class Subcommand {

	function __construct( $method, $command ) {
		$this->method = $method;
		$this->command = $command;
	}

	function show_usage( $prefix = 'usage: ' ) {
		$name = $this->get_name();
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $this->command $name $synopsis" );
	}

	function get_name() {
		$comment = $this->method->getDocComment();

		if ( preg_match( '/@subcommand\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return $this->method->name;
	}

	function invoke( $instance, $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );
		return $this->method->invoke( $instance, $args, $assoc_args );
	}

	protected function check_args( $args, $assoc_args ) {
		$accepted_params = $this->parse_synopsis( $this->get_synopsis() );

		$mandatory_positinal = wp_list_filter( $accepted_params, array(
			'type' => 'positional',
			'optional' => false
		) );

		if ( count( $args ) < count( $mandatory_positinal ) ) {
			$this->show_usage();
			exit(1);
		}

		$mandatory_assoc = wp_list_pluck( wp_list_filter( $accepted_params, array(
			'type' => 'assoc',
			'optional' => false
		) ), 'name' );

		$errors = array();

		foreach ( $mandatory_assoc as $key ) {
			if ( !isset( $assoc_args[ $key ] ) )
				$errors[] = "missing --$key parameter";
			elseif ( true === $assoc_args[ $key ] )
				$errors[] = "--$key parameter needs a value";
		}

		if ( empty( $errors ) )
			return;

		$this->show_usage();
		exit(1);
	}

	protected function get_synopsis() {
		$comment = $this->method->getDocComment();

		if ( !preg_match( '/@synopsis\s+([^\n]+)/', $comment, $matches ) )
			return false;

		return $matches[1];
	}

	protected function parse_synopsis( $synopsis ) {
		$patterns = self::get_patterns();

		$tokens = preg_split( '/[\s\t]+/', $synopsis );

		$params = array();

		foreach ( $tokens as $token ) {
			foreach ( $patterns as $regex => $desc ) {
				if ( preg_match( $regex, $token, $matches ) ) {
					$params[] = array_merge( $matches, $desc );
					break;
				}
			}
		}

		return $params;
	}

	private static function get_patterns() {
		$p_name = '(?P<name>[a-z-]+)';
		$p_value = '<(?P<value>[a-z-|]+)>';

		$param_types = array(
			'positional' => $p_value,
			'assoc' => "--$p_name=$p_value",
			'flag' => "--$p_name"
		);

		$patterns = array();

		foreach ( $param_types as $type => $pattern ) {
			$patterns[ "/^$pattern$/" ] = array(
				'type' => $type,
				'optional' => false
			);

			$patterns[ "/^\[$pattern\]$/" ] = array(
				'type' => $type,
				'optional' => true
			);
		}

		return $patterns;
	}
}


class SimpleSubcommand extends Subcommand {

	function show_usage( $prefix = 'usage: ' ) {
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $this->command $synopsis" );
	}
}

