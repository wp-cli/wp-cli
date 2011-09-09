<?php

WP_CLI::add_command('plugin', 'PluginCommand');
WP_CLI::add_command('plugins', 'PluginCommand');

require_once(ABSPATH.'wp-admin/includes/plugin.php');
require_once(ABSPATH.'wp-admin/includes/plugin-install.php');

/**
 * Returns current plugin version.
 *
 * @return string Plugin version
 */

class PluginCommand extends WP_Cli_Command {
	function parse_name($name) {
		$plugins = get_plugins('/'.$name);

		if(!empty($plugins)) {
			$keys = array_keys($plugins);
			$file = $name.'/'.$keys[0];
		}
		else {
			$plugins = get_plugins();
			if(isset($plugins[$name.'.php'])) {
				$file = $name.'.php';
			}
			else {
				die('This plugin does not exists: '.$name."\n");
			}
		}
		
		return $file;
	}
	
	function status($args) {
		if(!empty($args)) {
			$name = $args[0];
			$file = $this->parse_name($name);
			
			$details = $this->get_details($file);
			
			$this->_echo('Plugin '.$name.' details:');
			$this->_echo('    Active: '.((int) is_plugin_active($file)));
			if(is_multisite()) {
				$this->_echo('    Network: '.((int) is_plugin_active_for_network($file)));
			}
			$this->_echo('    Version: '.$details['Version']);
		}
	}
	
	function activate($args) {
		if(!empty($args)) {
			$name = $args[0];
			$file = $this->parse_name($name);
			
			if(is_plugin_active($file)) {
				$this->_echo('The plugin is already active: '.$name);
			}
			elseif(activate_plugin($file) === null) {
				$this->_echo('Plugin activated: '.$name);
			}
		}
	}
	
	function deactivate($args) {
		if(!empty($args)) {
			$name = $args[0];
			$file = $this->parse_name($name);
			
			if(is_plugin_inactive($file)) {
				$this->_echo('The plugin is already deactivate: '.$name);
			}
			elseif(deactivate_plugins($file) === null) {
				$this->_echo('Plugin deactivated: '.$name);
			}
		}
	}
	
	function install($args) {
		if(!empty($args)) {
			$name = $args[0];
			
	        $api = plugins_api('plugin_information', array('slug' => stripslashes($name)));
			$status = install_plugin_install_status($api);
			echo 'Updating '.$name.": ";

			switch($status['status']) {
				case 'update_available':
				case 'install':
					ob_start();
					if(!class_exists('Plugin_Upgrader')) {
						require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
					}
					$upgrader = new Plugin_Upgrader(new CLI_Upgrader_Skin);
					$result = $upgrader->install($api->download_link);
					$feedback = ob_get_clean();
					$this->_echo($feedback);
				break;
				case 'newer_installed':
					$this->_echo(sprintf('Newer Version (%s) Installed', $status['version']));
				break;
				case 'latest_installed':
					$this->_echo('Latest Version Installed');
					$file = $this->parse_name($name);
					
					if(is_plugin_inactive($file)) {
						$this->_echo('If you want to activate the plugin, run \'wp plugins activate '.$name.'\'');
					}
				break;
			}
		}
	}
	
	function delete($args) {
		if(!empty($args)) {
			$name = $args[0];
			$file = $this->parse_name($name);
			
			$success = delete_plugins(array($file));
		}
	}
	
	function update($args) {
		if(!empty($args)) {
			$name = $args[0];
			$file = $this->parse_name($name);
			
			wp_update_plugins();
			
			echo 'Updating '.$name.": ";
			ob_start();
			
			if(!class_exists('Plugin_Upgrader')) {
				require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
			}
			
			$upgrader = new Plugin_Upgrader(new CLI_Upgrader_Skin);
			$success = $upgrader->upgrade($file);
			
			$feedback = ob_get_clean();
			$this->_echo($feedback);
		}
		else {
			$this->_echo('Usage: wp plugins update <name>.');
		}
	}
	
	private function get_details($file) {
		$plugin_folder = get_plugins( '/' . plugin_basename(dirname($file)));
		$plugin_file = basename(($file));
		
		return $plugin_folder[$plugin_file];
	}
}