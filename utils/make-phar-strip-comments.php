<?php

/**
 * Strips comments and squeezes whitespace from source.
 *
 * Part of utils/make-phar.php.
 *
 * @param string $contents The source.
 * @param bool   $keep_doc_comments Whether to keep doc comments or not (for command sources).
 * @return string The source stripped of comments.
 */
function make_phar_strip_comments( $contents, $keep_doc_comments ) {
	$strs = array_map( function ( $t ) use ( $keep_doc_comments ) {
		if ( is_string( $t ) ) {
			return $t;
		}
		if ( T_COMMENT === $t[0] || ( ! $keep_doc_comments && T_DOC_COMMENT === $t[0] ) ) {
			if ( preg_match( '/copyright|licen[sc]e|\(c\)/i', $t[1] ) ) {
				// Keep for copyright reasons.
				return $t[1];
			}
			if ( preg_match( '/\@(BeforeSuite|BeforeScenario|AfterSuite|AfterScenario)/', $t[1] ) ) {
				return $t[1];
			}
			return str_repeat( "\n", substr_count( $t[1], "\n" ) ); // Strip everything but newlines.
		}
		if ( T_WHITESPACE === $t[0] ) {
			$str = preg_replace( '/[^\t\n]/', '', $t[1] ); // Keep tabs and newlines.
			return '' !== $str ? $str : ' '; // Keep at least one space.
		}
		return $t[1];
	}, token_get_all( $contents ) );

	return implode( '', $strs );
}
