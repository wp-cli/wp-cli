<?php

namespace WP_CLI\Compat;

use Mustache_Exception_InvalidArgumentException;
use Mustache_Exception_SyntaxException;
use Mustache_Tokenizer;

/**
 * Work-around extension for Mustache Tokenizer.
 */
class MustacheTokenizer extends Mustache_Tokenizer {

	/**
	 * Scan and tokenize template source.
	 *
	 * @param string $text       Mustache template source to tokenize.
	 * @param string $delimiters Optional. Pass initial opening and closing delimiters (default: null).
	 *
	 * @return array Set of Mustache tokens
	 * @throws Mustache_Exception_InvalidArgumentException When $delimiters string is invalid.
	 *
	 * @throws Mustache_Exception_SyntaxException When mismatched section tags are encountered.
	 */
	public function scan( $text, $delimiters = null ) {
		// Work-around while waiting for PHP 8.1 compat fix to be released.
		// See https://github.com/bobthecow/mustache.php/pull/380
		if ( ! is_string( $delimiters ) ) {
			$delimiters = '';
		}

		return parent::scan( $text, $delimiters );
	}
}
