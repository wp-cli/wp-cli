<?php

namespace WP_CLI;

class DocParser {

	protected $docComment;

	function __construct( $reflection ) {
		$this->docComment = $reflection->getDocComment();
	}

	function get_shortdesc() {
		if ( !preg_match( '/\* (\w.+)\n*/', $this->docComment, $matches ) )
			return false;

		return $matches[1];
	}

	function get_tag( $name ) {
		if ( preg_match( '/@' . $name . '\s+([a-z-]+)/', $this->docComment, $matches ) )
			return $matches[1];

		return false;
	}

	function get_synopsis() {
		if ( !preg_match( '/@synopsis\s+([^\n]+)/', $this->docComment, $matches ) )
			return false;

		return $matches[1];
	}
}

