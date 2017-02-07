<?php

namespace WP_CLI;

use Spyc;

/**
 * Parse command attributes from its PHPdoc.
 * Used to determine execution characteristics (arguments, etc.).
 */
class DocParser {

	/**
	 * @var string $docComment PHPdoc command for the command.
	 */
	protected $docComment;

	/**
	 * @param string $docComment
	 */
	public function __construct( $docComment ) {
		$this->docComment = self::remove_decorations( $docComment );
	}

	/**
	 * Remove unused cruft from PHPdoc comment.
	 *
	 * @param string $comment PHPdoc comment.
	 * @return string
	 */
	private static function remove_decorations( $comment ) {
		$comment = preg_replace( '|^/\*\*[\r\n]+|', '', $comment );
		$comment = preg_replace( '|\n[\t ]*\*/$|', '', $comment );
		$comment = preg_replace( '|^[\t ]*\* ?|m', '', $comment );

		return $comment;
	}

	/**
	 * Get the command's short description (e.g. summary).
	 *
	 * @return string
	 */
	public function get_shortdesc() {
		if ( !preg_match( '|^([^@][^\n]+)\n*|', $this->docComment, $matches ) )
			return '';

		return $matches[1];
	}

	/**
	 * Get the command's full description
	 *
	 * @return string
	 */
	public function get_longdesc() {
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

	/**
	 * Get the value for a given tag (e.g. "@alias" or "@subcommand")
	 *
	 * @param string $name Name for the tag, without '@'
	 * @return string
	 */
	public function get_tag( $name ) {
		if ( preg_match( '|^@' . $name . '\s+([a-z-_]+)|m', $this->docComment, $matches ) )
			return $matches[1];

		return '';
	}

	/**
	 * Get the command's synopsis.
	 *
	 * @return string
	 */
	public function get_synopsis() {
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
	public function get_arg_desc( $name ) {

		if ( preg_match( "/\[?<{$name}>.+\n: (.+?)(\n|$)/", $this->docComment, $matches ) ) {
			return $matches[1];
		}

		return '';

	}

	/**
	 * Get the arguments for a given argument.
	 *
	 * @param string $name Argument's doc name.
	 * @return mixed|null
	 */
	public function get_arg_args( $name ) {
		return $this->get_arg_or_param_args( "/^\[?<{$name}>.*/" );
	}

	/**
	 * Get the description for a given parameter.
	 *
	 * @param string $key Parameter's key.
	 * @return string
	 */
	public function get_param_desc( $key ) {

		if ( preg_match( "/\[?--{$key}=.+\n: (.+?)(\n|$)/", $this->docComment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Get the arguments for a given parameter.
	 *
	 * @param string $key Parameter's key.
	 * @return mixed|null
	 */
	public function get_param_args( $key ) {
		return $this->get_arg_or_param_args( "/^\[?--{$key}=.*/" );
	}

	/**
	 * Get the args for an arg or param
	 *
	 * @param string $regex Pattern to match against
	 * @return array|null Interpreted YAML document, or null.
	 */
	private function get_arg_or_param_args( $regex ) {
		$bits = explode( PHP_EOL, $this->docComment );
		$within_arg = $within_doc = false;
		$document = array();
		foreach( $bits as $bit ) {
			if ( preg_match( $regex, $bit ) ) {
				$within_arg = true;
			}

			if ( $within_arg && $within_doc && '---' === $bit ) {
				$within_doc = false;
			}

			if ( $within_arg && ! $within_doc && '---' === $bit ) {
				$within_doc = true;
			}

			if ( $within_doc ) {
				$document[] = $bit;
			}

			if ( $within_arg && '' === $bit ) {
				$within_arg = false;
				break;
			}
		}

		if ( $document ) {
			return Spyc::YAMLLoadString( implode( PHP_EOL, $document ) );
		}
		return null;
	}

}
