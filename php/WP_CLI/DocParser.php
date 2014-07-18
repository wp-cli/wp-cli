<?php

namespace WP_CLI;

class DocParser {

	protected $docComment;

	function __construct( $docComment ) {
		$this->docComment = self::remove_decorations( $docComment );
	}

	private static function remove_decorations( $comment ) {
		$comment = preg_replace( '|^/\*\*[\r\n]+|', '', $comment );
		$comment = preg_replace( '|\n[\t ]*\*/$|', '', $comment );
		$comment = preg_replace( '|^[\t ]*\* ?|m', '', $comment );

		return $comment;
	}

	function get_shortdesc() {
		if ( !preg_match( '|^([^@][^\n]+)\n*|', $this->docComment, $matches ) )
			return '';

		return $matches[1];
	}

	function get_longdesc() {
		$shortdesc = $this->get_shortdesc();
		if ( !$shortdesc )
			return '';

		$longdesc = substr( $this->docComment, strlen( $shortdesc ) );

		$lines = array();
		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( 0 === strpos( $line, '@' ) )
				break;

			$lines[] = $line;
		}
		$longdesc = trim( implode( $lines, "\n" ) );

		return $longdesc;
	}

	function get_tag( $name ) {
		if ( preg_match( '|^@' . $name . '\s+([a-z-_]+)|m', $this->docComment, $matches ) )
			return $matches[1];

		return '';
	}

	function get_synopsis() {
		if ( !preg_match( '|^@synopsis\s+(.+)|m', $this->docComment, $matches ) )
			return '';

		return $matches[1];
	}

	/**
	 * Get the description for a given argument.
	 *
	 * @param string $name Argument's doc name.
	 * @return string
	 */
	public function get_argdesc( $name ) {

		if ( preg_match( "/\[?<{$name}>[\S]+\n: (.+?)(\n|$)/s", $this->docComment, $matches ) ) {
			return $matches[1];
		}

		return '';

	}

	/**
	 * Get the description for a given parameter.
	 *
	 * @param string $key Parameter's key.
	 * @return string
	 */
	public function get_paramdesc( $key ) {

		if ( preg_match( "/\[?--{$key}=[\S]+\n: (.+?)(\n|$)/s", $this->docComment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}

