<?php

namespace WP_CLI\Dispatcher;

class MethodSubcommand extends Subcommand {

	function __construct( CommandContainer $parent, $class, \ReflectionMethod $method ) {
		$docparser = new \WP_CLI\DocParser( $method );

		$name = $docparser->get_tag( 'subcommand' );
		if ( !$name )
			$name = $method->name;

		$callable = new CallableMethod( $class, $method->name );

		parent::__construct( $parent, $name, $callable, $docparser );
	}

	function get_alias() {
		return $this->docparser->get_tag( 'alias' );
	}
}

