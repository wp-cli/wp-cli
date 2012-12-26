<?php

namespace WP_CLI\Dispatcher;

class MethodSubcommand extends Subcommand {

	function __construct( $class, $method, $parent ) {
		$callable = array( new $class, $method->name );

		$docparser = new \WP_CLI\DocParser( $method );

		$name = $docparser->get_tag( 'subcommand' );
		if ( !$name )
			$name = $method->name;

		parent::__construct( $name, $callable, $docparser, $parent );
	}

	function get_alias() {
		return $this->docparser->get_tag( 'alias' );
	}
}

