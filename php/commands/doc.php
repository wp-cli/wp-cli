<?php

class Doc_Command extends WP_CLI_Command {

	/**
	 * Get documentation on a function, class, method, or property.
	 *
	 * ## EXAMPLES
	 *
	 *     # get documentation for `get_posts` function
	 *     wp doc get_posts
	 *
	 *     # get documentation for `WP_Query::parse_query` method
	 *     wp doc WP_Query parse_query
	 *
	 * @synopsis <function-or-class> [<method-or-property>]
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'Reflection' ) ) {
			WP_CLI::error( 'The Reflection class is not available; you cannot use this command.' );
			return;
		}

		# class methods or properties can be passed as either `ClassName method` or `ClassName::method`
		if ( false !== strpos( $args[0], '::' ) ) {
			$args = explode( '::', $args[0] );
		}

		$doc = false;
		$command = '';
		$type = '';

		# Sort out what the user gave us; is it a function, class, method, or property?
		if ( 1 == count( $args ) ) {
			$command = $args[0];
			if ( function_exists( $args[0] ) ) {
				$type = 'function';
				$doc = self::get_function_doc( $args[0] );
			} elseif ( class_exists( $args[0] ) ) {
				$type = 'class';
				$doc = self::get_class_doc( $args[0] );
			}
		} else {
			$command = "{$args[0]}::{$args[1]}";
			if ( method_exists( $args[0], $args[1] ) ) {
				$type = 'class method';
				$doc = self::get_method_doc( $args[0], $args[1] );
			} elseif ( property_exists( $args[0], $args[1] ) ) {
				$type = 'class property';
				$doc = self::get_property_doc( $args[0], $args[1] );
			}
		}

		if ( false === $doc ) {
			\WP_CLI::error( "Sorry, '{$command}' does not appear to be a function, class, method, or property." );
		} elseif ( empty( $doc ) ) {
			WP_CLI::error( "No documentation found for {$type} '{$command}'" );
		} else {
			if ( 'function' == $type || 'class method' == $type )
				$command .= '()';
			$intro = "Documentation for {$type} '{$command}'";
			WP_CLI::success(
				"\n{$intro}\n"
				. preg_replace( '/./', '=', $intro ) . "\n"
				. self::normalize_doc_whitespace( $doc )
			);
		}
	}


	/**
	 * Get the PHPDoc block for a function.
	 *
	 * @param string $function A function name.
	 * @return string A PHPDoc doc block.
	 */
	private static function get_function_doc( $function ) {
		$r = new ReflectionFunction( $function );
		return $r->getDocComment();
	}


	/**
	 * Get the PHPDoc block for a class.
	 *
	 * @param string $class A class name.
	 * @return string A PHPDoc doc block.
	 */
	private static function get_class_doc( $class ) {
		$r = new ReflectionClass( $class );
		return $r->getDocComment();
	}


	/**
	 * Get the PHPDoc block for a given class and method.
	 *
	 * @param string $class A class name.
	 * @param string $method A method name.
	 * @return string A PHPDoc doc block.
	 */
	private static function get_method_doc( $class, $method ) {
		$r = new ReflectionMethod( $class, $method );
		return $r->getDocComment();
	}


	/**
	 * Get the PHPDoc block for a given class and property.
	 *
	 * @param string $class A class name.
	 * @param string $property A property name.
	 * @return string A PHPDoc doc block.
	 */
	private static function get_property_doc( $class, $property ) {
		$r = new ReflectionMethod( $class, $property );
		return $r->getDocComment();
	}


	/**
	 * Normalize the leading whitespace for the doc block.
	 *
	 * @param  string $doc a PHPDoc doc block.
	 * @return string
	 */
	private static function normalize_doc_whitespace( $doc ) {
		return preg_replace( '/^\s+(?=\*)/m', ' ', $doc );
	}
}

WP_CLI::add_command( 'doc', 'Doc_Command' );

