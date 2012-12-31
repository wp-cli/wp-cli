<?php

namespace WP_CLI\Dispatcher;

abstract class AbstractCommandContainer implements Command, CommandContainer {

	protected $subcommands = array();

	function add_subcommand( $name, Command $command ) {
		$this->subcommands[ $name ] = $command;
	}

	function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}
}

