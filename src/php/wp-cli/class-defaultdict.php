<?php

namespace WP_CLI\Utils;

class Defaultdict implements \ArrayAccess {

	private $container = array();
	private $default;

	public function __construct( $default ) {
		$this->default = $default;
	}

	public function offsetSet( $offset, $value ) {
		if ( is_null($offset) ) {
			trigger_error( sprintf( "Trying to use %s as a list.", __CLASS__ ), E_USER_WARNING );
			return;
		}

		$this->container[$offset] = $value;
	}

	public function offsetExists( $offset ) {
		return true;
	}

	public function offsetUnset( $offset ) {
		unset( $this->container[$offset] );
	}

	public function &offsetGet( $offset ) {
		if ( !isset( $this->container[$offset] ) ) {
			if ( is_callable($this->default) )
				$value = call_user_func( $this->default, $offset );
			else
				$value = $this->default;

			$this->container[$offset] = $value;
		}

		return $this->container[$offset];
	}
}

