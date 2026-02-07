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
	 * Build a map of all argument aliases to their canonical names.
	 *
	 * Parses the PHPdoc for all parameters that have an 'alias' attribute
	 * and builds a mapping from alias to canonical parameter name.
	 *
	 * This method uses a single-pass state machine to avoid nested iteration,
	 * improving performance from O(n²) to O(n) where n is the number of lines.
	 *
	 * @return array Map of alias => canonical_name
	 */
	public function get_arg_aliases() {
		$aliases       = [];
		$bits          = explode( "\n", $this->doc_comment );
		$current_param = null;
		$within_yaml   = false;
		$yaml_document = [];

		foreach ( $bits as $bit ) {
			$trimmed_bit = trim( $bit );

			// Check if we're starting or ending a YAML block
			if ( '---' === $trimmed_bit ) {
				if ( $within_yaml ) {
					// End of YAML block - parse it if we have a current parameter
					if ( $current_param && ! empty( $yaml_document ) ) {
						$yaml_string = implode( "\n", $yaml_document );
						$param_args  = Spyc::YAMLLoadString( $yaml_string );

						if ( $param_args && isset( $param_args['alias'] ) ) {
							$param_aliases = (array) $param_args['alias'];
							foreach ( $param_aliases as $alias ) {
								// Convert to string if not already
								$alias = (string) $alias;

								// Remove leading dashes if present
								$alias = ltrim( $alias, '-' );

								// Skip empty aliases
								if ( '' === $alias ) {
									continue;
								}

								$aliases[ $alias ] = $current_param;
							}
						}
					}
					$within_yaml   = false;
					$yaml_document = [];
				} else {
					// Start of YAML block - don't include the --- marker itself
					$within_yaml = true;
				}
				continue;
			}

			// If we're within a YAML block, collect the lines (preserving original text, not trimmed)
			if ( $within_yaml ) {
				$yaml_document[] = $bit;
				continue;
			}

			// Check if this line is a parameter definition
			// Match parameter definitions:
			// - [--param=<value>]
			// - --param=<value>
			// - [--param]
			// - --param
			if ( preg_match( '/^\[?--([a-z-_0-9]+)/', $trimmed_bit, $matches ) ) {
				$current_param = $matches[1];
				continue;
			}

			// Empty line ends the current parameter context (unless we're in YAML)
			if ( empty( $trimmed_bit ) ) {
				$current_param = null;
			}
		}

		return $aliases;
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
