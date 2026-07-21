<?php

namespace WP_CLI;

use Mustangostang\Spyc;

/**
 * Parse command attributes from its PHPdoc.
 * Used to determine execution characteristics (arguments, etc.).
 */
class DocParser {

	/**
	 * PHPdoc command for the command.
	 *
	 * @var string
	 */
	protected $doc_comment;

	/**
	 * @param string $doc_comment
	 */
	public function __construct( $doc_comment ) {
		/* Make sure we have a known line ending in document */
		$doc_comment       = str_replace( "\r\n", "\n", $doc_comment );
		$this->doc_comment = self::remove_decorations( $doc_comment );
	}

	/**
	 * Remove unused cruft from PHPdoc comment.
	 *
	 * @param string $comment PHPdoc comment.
	 * @return string
	 */
	private static function remove_decorations( $comment ) {
		$comment = (string) preg_replace( '|^/\*\*[\r\n]+|', '', $comment );
		$comment = (string) preg_replace( '|\n[\t ]*\*/$|', '', $comment );
		$comment = (string) preg_replace( '|^[\t ]*\* ?|m', '', $comment );

		return $comment;
	}

	/**
	 * Get the command's short description (e.g. summary).
	 *
	 * @return string
	 */
	public function get_shortdesc() {
		if ( ! preg_match( '|^([^@][^\n]+)\n*|', $this->doc_comment, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Get the command's full description
	 *
	 * @return string
	 */
	public function get_longdesc() {
		$shortdesc = $this->get_shortdesc();
		if ( ! $shortdesc ) {
			return '';
		}

		$longdesc = substr( $this->doc_comment, strlen( $shortdesc ) );

		$lines = [];
		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( 0 === strpos( $line, '@' ) ) {
				break;
			}

			$lines[] = $line;
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Get the value for a given tag (e.g. "@alias" or "@subcommand")
	 *
	 * @param string $name Name for the tag, without '@'
	 * @return string
	 */
	public function get_tag( $name ) {
		if ( preg_match( '|^@' . $name . '\s+([a-z-_0-9]+)|m', $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Check if a given tag exists (e.g. "@skipglobalargcheck")
	 *
	 * Useful for checking the presence of valueless tags in PHPdoc.
	 *
	 * @param string $name Name for the tag, without '@'
	 * @return bool True if the tag exists, false otherwise.
	 */
	public function has_tag( $name ) {
		return (bool) preg_match( '/^\s*\*?\s*@' . preg_quote( $name, '/' ) . '\b/m', $this->doc_comment );
	}

	/**
	 * Get the deprecation message for this command.
	 *
	 * @return string
	 */
	public function get_deprecation_message() {
		if ( ! preg_match( '|^@deprecated(?:[ \t]+(.+))?[ \t]*$|m', $this->doc_comment, $matches ) ) {
			return '';
		}

		return isset( $matches[1] ) ? trim( $matches[1] ) : '';
	}

	/**
	 * Get deprecated assoc arguments from a synopsis and docparser.
	 *
	 * @param string|array   $synopsis  Synopsis string or parsed specification.
	 * @param DocParser|null $docparser DocParser instance.
	 * @return array<string, string> Deprecated argument names and their deprecation messages.
	 */
	public static function get_deprecated_assoc_args( $synopsis, $docparser ) {
		if ( ! $docparser || empty( $synopsis ) ) {
			return [];
		}

		$synopsis_spec         = is_array( $synopsis ) ? $synopsis : SynopsisParser::parse( $synopsis );
		$deprecated_assoc_args = [];

		foreach ( $synopsis_spec as $spec ) {
			if ( 'assoc' !== $spec['type'] ) {
				continue;
			}

			$spec_args = $docparser->get_param_args( $spec['name'] );
			if ( ! isset( $spec_args['deprecated'] ) || false === $spec_args['deprecated'] ) {
				continue;
			}

			$deprecation_message = is_string( $spec_args['deprecated'] ) ? trim( $spec_args['deprecated'] ) : '';

			$deprecated_assoc_args[ $spec['name'] ] = $deprecation_message;
		}

		return $deprecated_assoc_args;
	}

	/**
	 * Get the command's synopsis.
	 *
	 * @return string
	 */
	public function get_synopsis() {
		if ( ! preg_match( '|^@synopsis\s+(.+)|m', $this->doc_comment, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Get the description for a given argument.
	 *
	 * @param string $name Argument's doc name.
	 * @return string
	 */
	public function get_arg_desc( $name ) {

		if ( preg_match( "/\[?<{$name}>.+\n: (.+?)(\n|$)/", $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Get the arguments for a given argument.
	 *
	 * @param string $name Argument's doc name.
	 * @return array|null
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

		if ( preg_match( "/\[?--{$key}=.+\n: (.+?)(\n|$)/", $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Get the arguments for a given parameter.
	 *
	 * @param string $key Parameter's key.
	 * @return array|null
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
		$bits       = explode( "\n", $this->doc_comment );
		$within_arg = false;
		$within_doc = false;
		$document   = [];
		foreach ( $bits as $bit ) {
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
			return Spyc::YAMLLoadString( implode( "\n", $document ) );
		}
		return null;
	}
}
