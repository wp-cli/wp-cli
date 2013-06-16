<?php

namespace WP_CLI;

// Adapted from WP_Export_Split_Files_Writer
class VerboseExportWriter extends \WP_Export_Base_Writer {
	private $result = '';
	private $f;
	private $next_file_number = 0;
	private $current_file_size = 0;

	function __construct( $formatter, $writer_args = array() ) {
		parent::__construct( $formatter );
		//TODO: check if args are not missing
		$this->max_file_size = is_null( $writer_args['max_file_size'] ) ? 15 * MB_IN_BYTES : $writer_args['max_file_size'];
		$this->destination_directory = $writer_args['destination_directory'];
		$this->filename_template = $writer_args['filename_template'];
		$this->before_posts_xml = $this->formatter->before_posts();
		$this->after_posts_xml = $this->formatter->after_posts();
	}

	public function export() {
		$this->start_new_file();
		foreach( $this->formatter->posts() as $post_xml ) {
			if ( ( $this->current_file_size + strlen( $post_xml ) ) > $this->max_file_size ) {
				$this->start_new_file();
			}
			$this->write( $post_xml );
		}
		$this->close_current_file();
	}

	protected function write( $xml ) {
		$res = fwrite( $this->f, $xml);
		if ( false === $res ) {
			throw new WP_Export_Exception( __( 'WP Export: error writing to export file.' ) );
		}
		$this->current_file_size += strlen( $xml );
	}

	private function start_new_file() {
		if ( $this->f ) {
			$this->close_current_file();
		}
		$file_path = $this->next_file_path();
		$this->f = fopen( $file_path, 'w' );
		if ( !$this->f ) {
			throw new WP_Export_Exception( sprintf( __( 'WP Export: error opening %s for writing.' ), $file_path ) );
		}
		\WP_CLI::log( sprintf( "Started writing to %s", $file_path ) );
		$this->current_file_size = 0;
		$this->write( $this->before_posts_xml );
	}

	private function close_current_file() {
		if ( !$this->f ) {
			return;
		}
		$this->write( $this->after_posts_xml );
		fclose( $this->f );
	}

	private function next_file_name() {
		$next_file_name = sprintf( $this->filename_template, $this->next_file_number );
		$this->next_file_number++;
		return $next_file_name;
	}

	private function next_file_path() {
		return untrailingslashit( $this->destination_directory ) . DIRECTORY_SEPARATOR . $this->next_file_name();
	}
}

