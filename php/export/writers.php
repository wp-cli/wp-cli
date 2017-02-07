<?php
abstract class WP_Export_Base_Writer {
	protected $formatter;

	function __construct( $formatter ) {
		$this->formatter = $formatter;
	}

	public function export() {
		$this->write( $this->formatter->before_posts() );
		foreach( $this->formatter->posts() as $post_in_wxr ) {
			$this->write( $post_in_wxr );
		}
		$this->write( $this->formatter->after_posts() );
	}

	abstract protected function write( $xml );
}

class WP_Export_XML_Over_HTTP extends WP_Export_Base_Writer {
	private $file_name;

	function __construct( $formatter, $file_name ) {
		parent::__construct( $formatter );
		$this->file_name = $file_name;
	}

	public function export() {
		try {
			$export = $this->get_export();
			$this->send_headers();
			echo $export;
		} catch ( WP_Export_Exception $e ) {
			$message = apply_filters( 'export_error_message', $e->getMessage() );
			wp_die( $message, __( 'Export Error' ), array( 'back_link' => true ) );
		} catch ( WP_Export_Term_Exception $e ) {
			do_action( 'export_term_orphaned', $this->formatter->export->missing_parents );
			$message = apply_filters( 'export_term_error_message', $e->getMessage() );
			wp_die( $message, __( 'Export Error' ), array( 'back_link' => true ) );
		}
	}

	protected function write( $xml ) {
		$this->result .= $xml;
	}

	protected function get_export() {
		$this->result = '';
		parent::export();
		return $this->result;
	}

	protected function send_headers() {
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $this->file_name );
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
	}
}

class WP_Export_Returner extends WP_Export_Base_Writer {
	private $result = '';

	public function export() {
		$this->private = '';
		try { 
			parent::export();
		} catch ( WP_Export_Exception $e ) {
			$message = apply_filters( 'export_error_message', $e->getMessage() );
			return new WP_Error( 'wp-export-error', $message );
			
		} catch ( WP_Export_Term_Exception $e ) {
			do_action( 'export_term_orphaned', $this->formatter->export->missing_parents );
			$message = apply_filters( 'export_term_error_message', $e->getMessage() );
			return new WP_Error( 'wp-export-error', $message );
		}
		return $this->result;
	}
	protected function write( $xml ) {
		$this->result .= $xml;
	}
}

class WP_Export_File_Writer extends WP_Export_Base_Writer {
	private $f;
	private $file_name;

	public function __construct( $formatter, $file_name ) {
		parent::__construct( $formatter );
		$this->file_name = $file_name;
	}

	public function export() {
		$this->f = fopen( $this->file_name, 'w' );
		if ( !$this->f ) {
			throw new WP_Export_Exception( sprintf( __( 'WP Export: error opening %s for writing.' ), $this->file_name ) );
		}

		try { 
			parent::export();
		} catch ( WP_Export_Exception $e ) {
			throw $e;
		} catch ( WP_Export_Term_Exception $e ) {
			throw $e;
		}

		fclose( $this->f );
	}

	protected function write( $xml ) {
		$res = fwrite( $this->f, $xml);
		if ( false === $res ) {
			throw new WP_Export_Exception( __( 'WP Export: error writing to export file.' ) );
		}
	}
}

class WP_Export_Split_Files_Writer extends WP_Export_Base_Writer {
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
			if ( $this->current_file_size + strlen( $post_xml ) > $this->max_file_size ) {
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
		do_action( 'wp_export_new_file', $file_path );
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
