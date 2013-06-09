<?php

namespace WP_CLI\Dispatcher;

abstract class AbstractCommandContainer implements Command, CommandContainer {

	protected $subcommands = array();

	function get_synopsis() {
		return '';
	}

	function invoke( $args, $assoc_args ) {
		$this->show_usage();
	}

	function add_subcommand( $name, Command $command ) {
		$this->subcommands[ $name ] = $command;
	}

	function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}
}

