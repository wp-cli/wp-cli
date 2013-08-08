<?php
/**
 * Proof of concept `server` command.
 * 
 * Uses [reactphp](http://reactphp.org) event loop and socket server to handle
 * socket operations. `wp server` starts a process that opens a server on the
 * reuqested port (--port=3000) and can handle concurrent requests.
 * 
 * When requests come in it spawns a `wp server worker` process and pipes the request to
 * that process so it can handle building the response.
 *
 * Potentially the server could spawn worker processes ahead of time so they are
 * ready to handle requests as they come in.
 *
 */
class Server_Command extends \WP_CLI_Command {

	/**
	 * HTTP Server for WP CLI
	 * 
	 * ## DESCRIPTION
	 * 
	 * `wp server` allows you boot a web server running wp
	 *
	 * --port=<port>
	 * : Bind to the given port (default: 4000)
	 * @synopsis [--port=<port>]
	 */
	public function __invoke( $_, $assoc_args ){

		// determien which port to use
		$port = 4000;
		if ( isset( $assoc_args['port'] ) ) {
			$port = $assoc_args['port'];
		}

		if ( isset( $_[0] ) && $_[0] == 'worker' ) {
			$this->worker();
		} else {
			$this->server( $port );
		}

	}

	/**
	 * Starts a socket server on the given port. Currently pipes the raw socket
	 * data straight to the child process. React\Http\Server can be used here
	 * on top of the socket which can allow static file serving and request body
	 * parsing to be handed off to the worker thread. 
	 */
	public function server( $port ){
		// inform the user that we booted the server and on which port
		WP_CLI::line("Booting WordPress server on port $port");

		// create the react event loop and socket server
		$loop = React\EventLoop\Factory::create();
		$socket = new React\Socket\Server($loop);

		// handle connections to the socket server using the callback
		$socket->on( 'connection', function( $conn ) use ( $loop ){

			$start = microtime( true );

			// build the command we're using for the child process: `wp server worker`
			$path = ABSPATH;
			$args = array( $_SERVER['WP_CLI_PHP_USED'], $_SERVER['argv'][0], "--path=$path server worker" );
			$cmd = implode( " ", $args );

			// start the new Process stream with the given command
			// we can potentially provide environment variables here for passing in
			// parsed request bodies
			$proc = new WP_CLI\Process( $cmd );
			
			// start the child process command
			$proc->start( $loop );

			// store the stdout of the child process in a string
			$buffer = "";
			$header = null;

			// as the child writes to stdout collect the data into the buffer
			$proc->stdout->on( 'data', function( $data ) use ( &$buffer ){
				$buffer .= $data;
			});

			// if the child process writes to stderr, write the error to the console
			$proc->stderr->on( 'data', function( $data ) {
				if ( strlen( $data ) > 0) {
					WP_CLI::line( "Error: " . $data );
				}
			});

			// write the connecting client's data to the worker's stdin
			$conn->on( 'data', function( $data ) use ( $proc, &$header ){
				// pull off the header and report that we've received a request to the
				// terminal
				if ( $header == null ) {
					$header = substr( $data, 0, strpos( $data, "\r\n") );
					WP_CLI::line("Request: $header");
				}
				// pipe the data into the child process
				$proc->stdin->write( $data );
			});

			// when the process is done send a http response back along with the data
			// we're just writing a 200 status response header with the bare minimum
			// headers to display a response in the browser for now.
			$proc->on( 'exit', function() use ( $conn, &$buffer, &$start ){
				$length = strlen( $buffer );
				$conn->write("HTTP/1.1 200 OK\r\n");
				$conn->write("Content-Type:text/html\r\n");
				$conn->write("Content-Length:$length\r\n");
				$conn->write("\r\n\r\n");
				$conn->write($buffer);
				$conn->end();
				$ms = round( ( microtime( true ) - $start ) * 1000);
				// report how long it took to render the response
				WP_CLI::line("Response in {$ms} ms");
			});

		});

		// start accepting connections on the port
		$socket->listen($port);

		// start the event loop
		$loop->run();
		
	}

	public function worker(){
		// send all the errors back to the main process over stderr
		set_error_handler( function( $errno, $err_str, $err_file, $errline ){
			fwrite( STDERR, "$errno: $err_str in $err_file at #$errline" );
		} );
		// enable themes
		define('WP_USE_THEMES', true);
		// render the template
		include( ABSPATH . WPINC . '/template-loader.php' );
		// and we're done
		restore_error_handler();
	}

}

\WP_CLI::add_command( 'server', 'Server_Command' );
