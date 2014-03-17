<?php

namespace WP_CLI;

class Completions {

	private $words;
	private $opts = array();

	function __construct( $line ) {
		// TODO: properly parse single and double quotes
		$this->words = explode( ' ', $line );

		// first word is always `wp`
		array_shift( $this->words );

		// last word is either empty or an incomplete subcommand
		$this->cur_word = end( $this->words );

		$r = $this->get_command( $this->words );
		if ( !is_array( $r ) ) {
			return;
		}

		list( $command, $args, $assoc_args ) = $r;

		$spec = SynopsisParser::parse( $command->get_synopsis() );

		foreach ( $spec as $arg ) {
			if ( $arg['type'] == 'positional' && $arg['name'] == 'file' ) {
				$this->add( '<file> ' );
				return;
			}
		}

		if ( $command->can_have_subcommands() ) {
			foreach ( $command->get_subcommands() as $name => $_ ) {
				$this->add( "$name " );
			}
		} else {
			foreach ( $spec as $arg ) {
				if ( in_array( $arg['type'], array( 'flag', 'assoc' ) ) ) {
					if ( isset( $assoc_args[ $arg['name'] ] ) ) {
						continue;
					}

					$opt = "--{$arg['name']}";

					if ( $arg['type'] == 'flag' ) {
						$opt .= ' ';
					} elseif ( !$arg['value']['optional'] ) {
						$opt .= '=';
					}

					$this->add( $opt );
				}
			}
		}

	}

	private function get_command( $words ) {
		$positional_args = $assoc_args = array();

		foreach ( $words as $arg ) {
			if ( preg_match( '|^--([^=]+)=?|', $arg, $matches ) ) {
				$assoc_args[ $matches[1] ] = true;
			} else {
				$positional_args[] = $arg;
			}
		}

		$r = \WP_CLI::get_runner()->find_command_to_run( $positional_args );
		if ( !is_array( $r ) && array_pop( $positional_args ) == $this->cur_word ) {
			$r = \WP_CLI::get_runner()->find_command_to_run( $positional_args );
		}

		if ( !is_array( $r ) ) {
			return $r;
		}

		list( $command, $args ) = $r;

		return array( $command, $args, $assoc_args );
	}

	private function add( $opt ) {
		if ( $this->cur_word !== '' ) {
			if ( strpos( $opt, $this->cur_word ) !== 0 ) {
				return;
			}
		}

		$this->opts[] = $opt;
	}

	public function render() {
		foreach ( $this->opts as $opt ) {
			\WP_CLI::line( $opt );
		}
	}
}

