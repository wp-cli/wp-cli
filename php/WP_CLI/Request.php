<?php

namespace WP_CLI;

/**
 * Interprets CLI arguments to a structured representation
 */
class Request implements \ArrayAccess {

	protected $args = array(
		'runtime'    => array(),
		'positional' => array(),
		'assoc'      => array(),
	);

	protected $specs = array(
		'runtime'    => null,
	);

	/**
	 * Create a new WP_CLI\Request object from supplied $args.
	 *
	 * @param array $args
	 */
	public function __construct( $args ) {
		$runtime = array();
		foreach( $args as $key => $arg ) {
			if ( 0 !== strpos( $arg, '--' ) ) {
				break;
			}
			$runtime[ $key ] = $arg;
			unset( $args[ $key ] );
		}
		list( $_, $this->args['runtime'] ) = Configurator::extract_assoc( $runtime );
		list( $this->args['positional'], $this->args['assoc'] ) = Configurator::extract_assoc( $args );
		$this->specs['runtime'] = include WP_CLI_ROOT . '/php/config-spec.php';
	}

	/**
	 * Get a new WP_CLI\Request object from the $argv global.
	 *
	 * @access public
	 *
	 * @global array $argv
	 *
	 * @return WP_CLI\Request
	 */
	public static function get_from_argv() {
		$argv = $GLOBALS['argv'];
		// Ignore WP-CLI binary
		if ( isset( $argv[0] ) ) {
			unset( $argv[0] );
		}
		return new self( $argv );
	}

	/**
	 * Get runtime args, with their spec applied
	 *
	 * @return array
	 */
	public function get_runtime_args() {
		$args = array();
		foreach( $this->args['runtime'] as $param ) {
			list( $key, $value ) = $param;
			if ( ! isset( $this->specs['runtime'][ $key ] ) ) {
				continue;
			}
			if ( ! empty( $this->specs['runtime'][ $key ]['multiple'] ) ) {
				if ( isset( $args[ $key ] ) ) {
					$args[ $key ] = array_merge( $args[ $key ], array( $value ) );
				} else {
					$args[ $key ] = array( $value );
				}
			} else {
				$args[ $key ] = $value;
			}
		}
		// Backfill defaults
		foreach( $this->specs['runtime'] as $key => $spec ) {
			if ( isset( $args[ $key ] ) || ! isset( $spec['default'] ) ) {
				continue;
			}
			$args[ $key ] = $spec['default'];
		}
		return $args;
	}

	/**
	 * Get the value of a specific runtime arg, if set.
	 *
	 * @return mixed|null Value if set, null otherwise.
	 */
	public function get_runtime_arg( $key ) {
		$args = $this->get_runtime_args();
		return isset( $args[ $key ] ) ? $args[ $key ] : null;
	}

	public function get_positional_args() {
		return $this->args['positional'];
	}

	public function get_assoc_args() {
		$args = array();
		foreach( $this->args['assoc'] as $param ) {
			list( $key, $value ) = $param;
			if ( ! empty( $this->specs['assoc'][ $key ]['multiple'] ) ) {
				if ( isset( $args[ $key ] ) ) {
					$args[ $key ] = array_merge( $args[ $key ], array( $value ) );
				} else {
					$args[ $key ] = array( $value );
				}
			} else {
				$args[ $key ] = $value;
			}
		}
		return $args;
	}

	/**
	 * Get the value of a specific assoc arg, if set.
	 *
	 * @return mixed|null Value if set, null otherwise.
	 */
	public function get_assoc_arg( $key ) {
		$args = $this->get_assoc_args();
		return isset( $args[ $key ] ) ? $args[ $key ] : null;
	}

	/**
	 * Retreive an argument from the request.
	 *
	 * @param string $key Parameter name.
	 * @return mixed|null Value if set, null otherwise.
	 */
	public function get_arg( $key ) {
		$order = $this->get_parameter_order();
		foreach ( $order as $type ) {
			$method = "get_{$type}_args";
			$args = $this->$method();
			if ( isset( $args[ $key ] ) ) {
				return $args[ $key ];
			}
		}
		return null;
	}

	/**
	 * Retrieves the parameter priority order.
	 *
	 * Used when checking parameters in get_param().
	 *
	 * @since 4.4.0
	 * @access protected
	 *
	 * @return array List of types to check, in order of priority.
	 */
	protected function get_parameter_order() {
		$order = array();
		$order[] = 'runtime';
		$order[] = 'positional';
		$order[] = 'assoc';
		return $order;
	}

	/**
	 * Checks if a parameter is set.
	 *
	 * @param string $offset Parameter name.
	 * @return bool Whether the parameter is set.
	 */
	public function offsetExists( $offset ) {
		$order = $this->get_parameter_order();
		foreach ( $order as $type ) {
			$method = "get_{$type}_args";
			$args = $this->$method();
			if ( isset( $args[ $offset ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieves a parameter from the request.
	 *
	 * @param string $offset Parameter name.
	 * @return mixed|null Value if set, null otherwise.
	 */
	public function offsetGet( $offset ) {
		return $this->get_arg( $offset );
	}

	/**
	 * Sets a parameter on the request.
	 *
	 * @param string $offset Parameter name.
	 * @param mixed  $value  Parameter value.
	 */
	public function offsetSet( $offset, $value ) {
		return null;
	}

	/**
	 * Removes an arg from the request.
	 *
	 * @param string $offset Parameter name.
	 */
	public function offsetUnset( $offset ) {
		$order = $this->get_parameter_order();

		// Remove the offset from every group.
		foreach ( $order as $type ) {
			if ( in_array( $type, array( 'runtime', 'assoc' ) ) ) {
				foreach( $this->args[ $type ] as $key => $values ) {
					if ( $values[0] === $offset ) {
						unset( $this->args[ $type ][ $key ] );
					}
				}
			} else {
				unset( $this->args[ $type ][ $offset ] );
			}
		}
	}

}
