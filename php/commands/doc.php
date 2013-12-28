<?php

class Doc_Command extends WP_CLI_Command {

	/**
	 * The file name containing the function, class, or method found.
	 *
	 * @var string
	 */
	private static $file;


	/**
	 * The line number where the function, class, or method was found.
	 *
	 * @var int
	 */
	private static $line;


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
			$args[1] = ltrim( $args[1], '$' );
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
			WP_CLI::error( "Sorry, '{$command}' does not appear to be a function, class, method, or property." );
		} elseif ( empty( $doc ) ) {
			WP_CLI::error( "No documentation found for {$type} '{$command}'" );
		} else {
			if ( 'function' == $type || 'class method' == $type )
				$command .= '()';
			$intro = "Documentation for {$type} '{$command}'";

			if ( self::$file && self::$line )
				$intro .= sprintf( " in %s:%d", self::$file, self::$line );

			$intro .= "\n" . preg_replace( '/./', '=', $intro ) . "\n\n";

			self::pass_through_pager( $intro . self::format_comment( $doc ) );
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
		self::get_location( $r );
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
		self::get_location( $r );
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
		self::get_location( $r );
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
		$r = new ReflectionProperty( $class, $property );
		return $r->getDocComment();
	}


	/**
	 * Format the comment for pleasant viewing.
	 *
	 * @param  string $doc a PHPDoc doc block.
	 * @return string
	 */
	private static function format_comment( $doc ) {
		# Remove leading whitespace and comment characters in docs
		$doc = preg_replace( '/^\s+(?=\*)/m', ' ', $doc );
		$doc = preg_replace( '#^(/\*\*\R*| \*[ /]?)#m', '', $doc );

		# Pull param and return tags out and format those special
		$doc = preg_replace( '/^@(param|return)((?: [^ ]+)?(?: \$[_a-z0-9]+)?(?: Optional\.)?)\s+/mi', "\n## $1\t$2\n\n", $doc );

		# Make all the other miscellaneous tags headings
		$doc = preg_replace( '/^@(\w+)\s+/mi', "\n## $1\n\n", $doc );

		# Indent all text that isn't a heading
		$doc = preg_replace( '/^(?=[^#]{2})/m', '  ', $doc );

		# Convert the headings to uppercase and colorize
		$doc = preg_replace_callback( '/^## ([a-z]+)/mi', create_function( '$m', 'return strtoupper( $m[1] );'), $doc );
		$doc = preg_replace( '/^## ([A-Z ]+)/m', WP_CLI::colorize( '%9\1%n' ), $doc );

		# Convert code tags to backticks
		$doc = preg_replace( '#<code>(.*?)</code>#msi', '`$1`', $doc );

		# Whew! Return the formatted doc
		return $doc;
	}


	/**
	 * Finds the file and line number of a class, function, or method.
	 *
	 * @param object $r A ReflectionFunction, ReflectionClass, or ReflectionMethod object.
	 * @return void
	 */
	private static function get_location( $r ) {
		if ( method_exists( $r, 'getFileName' ) ) {
			self::$file = str_replace( ABSPATH, '', $r->getFileName() );
			self::$line = $r->getStartLine();
		}
	}


	private static function pass_through_pager( $out ) {
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			// no paging for Windows cmd.exe; sorry
			echo $out;
			return 0;
		}

		// convert string to file handle
		$fd = fopen( "php://temp", "r+" );
		fputs( $fd, $out );
		rewind( $fd );

		$descriptorspec = array(
			0 => $fd,
			1 => STDOUT,
			2 => STDERR
		);

		return proc_close( proc_open( 'less -r', $descriptorspec, $pipes ) );
	}

}

WP_CLI::add_command( 'doc', 'Doc_Command' );

