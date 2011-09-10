<?php

WP_CLI::addCommand('core', 'CoreCommand');

class CoreCommand extends WP_CLI_Command {
	function update($args) {
		echo 'Updating the core: ';
		if(!class_exists('Core_Upgrader')) {
			require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
		}
		ob_start();
		$upgrader = new Core_Upgrader(new CLI_Upgrader_Skin);
		$result = $upgrader->upgrade($current);
		$feedback = ob_get_clean();
		$this->_echo($feedback);
		
		// Borrowed verbatim from wp-admin/update-core.php
		if(is_wp_error($result) ) {
			$this->_echo(error_to_string($result));
			if('up_to_date' != $result->get_error_code()) {
				$this->_echo('Installation Failed');
			}
		} 
		else {
			$this->_echo('WordPress upgraded successfully');
		}
	}
}