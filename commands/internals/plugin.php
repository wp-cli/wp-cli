<?php

// Add the command to the wp-cli
WP_CLI::addCommand('plugin', 'PluginCommand');

// Do the required includes
require_once(ABSPATH.'wp-admin/includes/plugin.php');
require_once(ABSPATH.'wp-admin/includes/plugin-install.php');

/**
 * Implement plugin command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Andreas Creten
 */
class PluginCommand extends WP_CLI_Command {
	/**
	 * Get the status of one plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function status($args) {
		if(!empty($args)) {
			// Get the plugin name from the arguments
			$name = $this->check_name($args);

			// Get the plugin file name
			$file = $this->parse_name($name);

			// Get the plugin details
			$details = $this->get_details($file);

			// Display the plugin details
			WP_CLI::line('Plugin %2'.$name.'%n details:');
			WP_CLI::line('    Active: '.((int) is_plugin_active($file)));
			if(is_multisite()) {
				WP_CLI::line('    Network: '.((int) is_plugin_active_for_network($file)));
			}
			WP_CLI::line('    Version: '.$details['Version']);
		}
		else {
			// Get the list of plugins
			$plugins = get_plugins();

			// Get list of mu plugins
			$mu_plugins = get_mu_plugins();

			// Merge the two plugin arrays
			$plugins = array_merge($plugins, $mu_plugins);

			// Print the header
			WP_CLI::line('Installed plugins:');

			// Show the list if themes
			foreach ($plugins as $file => $plugin) {
				// Check plugin status
				$network = is_plugin_active_for_network($file);
				$status = is_plugin_active($file);
				$must_use = isset($mu_plugins[$file]);
				$name = dirname($file) ? dirname($file) : str_replace('.php', '', basename($file));

				WP_CLI::line('  '.($must_use ? '%cM' : ($status ? ($network ? '%bN' : '%gA') : 'I')).' '.$name.'%n');
			}

			// Print the footer
			WP_CLI::line();
			WP_CLI::line('Legend: A = Active, I = Inactive, M = Must Use, N = Network Active');
		}
	}

	/**
	 * Activate a plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function activate($args) {
		// Get the plugin name from the arguments
		$name = $this->check_name($args);

		// Get the plugin file name
		$file = $this->parse_name($name);

		// Check if the plugin is already active
		if(is_plugin_active($file)) {
			WP_CLI::error('The plugin is already active: '.$name);
		}
		// Try to activate the plugin
		elseif(activate_plugin($file) === null) {
			WP_CLI::success('Plugin activated: '.$name);
		}
		else {
			WP_CLI::error('The plugin could not be activated: '.$name);
		}
	}

	/**
	 * Deactivate a plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function deactivate($args) {
		// Get the plugin name from the arguments
		$name = $this->check_name($args);

		// Get the plugin file name
		$file = $this->parse_name($name);

		// Check if the plugin is already deactivated
		if(is_plugin_inactive($file)) {
			WP_CLI::error('The plugin is already deactivated: '.$name);
		}
		// Try to deactivate the plugin
		elseif(deactivate_plugins($file) === null) {
			WP_CLI::success('Plugin deactivated: '.$name);
		}
		else {
			WP_CLI::error('Could not deactivate this plugin: '.$name);
		}
	}

	/**
	 * Install a new plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function install($args) {
		// Get the plugin name from the arguments
		$name = $this->check_name($args);

		// Get the plugin file name
		$file = $this->parse_name($name, false);

		// Force WordPress to update the plugin list
		wp_update_plugins();

		// Get plugin info from the WordPress servers
	    $api = plugins_api('plugin_information', array('slug' => stripslashes($name)));
		$status = install_plugin_install_status($api);

		WP_CLI::line('Installing '.$api->name.' ('.$api->version.')');

		// Check what to do
		switch($status['status']) {
			case 'update_available':
			case 'install':
				if(!class_exists('Plugin_Upgrader')) {
					require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
				}

				// Install the plugin
				ob_start('strip_tags');
				$upgrader = new Plugin_Upgrader(new CLI_Upgrader_Skin);
				$result = $upgrader->install($api->download_link);
				$feedback = ob_get_clean();

				if($result !== null) {
					WP_CLI::error($result);
				}
				else {
					WP_CLI::line();
					WP_CLI::line(strip_tags(str_replace(array('&#8230;', 'Plugin installed successfully.'), array(" ...\n", ''), html_entity_decode($feedback))));
					WP_CLI::success('The plugin is successfully installed');
				}
			break;
			case 'newer_installed':
				WP_CLI::error(sprintf('Newer version (%s) installed', $status['version']));
			break;
			case 'latest_installed':
				WP_CLI::error('Latest version already installed');

				if(is_plugin_inactive($file)) {
					WP_CLI::warning('If you want to activate the plugin, run: %2wp plugin activate '.$name.'%n');
				}
			break;
		}
	}

	/**
	 * Delete a plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function delete($args) {
		// Get the plugin name from the arguments
		$name = $this->check_name($args);

		// Get the plugin file name
		$file = $this->parse_name($name);

		if(delete_plugins(array($file))) {
			WP_CLI::success('The plugin is successfully deleted.');
		}
		else {
			WP_CLI::error('There was an error while deleting the plugin.');
		}
	}

	/**
	 * Update a plugin
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	function update($args) {
		// Get the plugin name from the arguments
		$name = $this->check_name($args);

		// Get the plugin file name
		$file = $this->parse_name($name);

		// Force WordPress to update the plugin list
		wp_update_plugins();

		if(!class_exists('Plugin_Upgrader')) {
			require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
		}

		WP_CLI::line('Updating '.$name);

		// Upgrading the plugin
		ob_start('strip_tags');
		$upgrader = new Plugin_Upgrader(new CLI_Upgrader_Skin);
		$result = $upgrader->upgrade($file);
		$feedback = ob_get_clean();

		if($result !== null) {
			WP_CLI::error($feedback);
		}
		else {
			WP_CLI::line();
			WP_CLI::line(html_entity_decode(strip_tags($feedback)));
			WP_CLI::success('The plugin is successfully updated.');
		}
	}

	/* PRIVATES */

	/**
	 * Get the details of a plugin
	 *
	 * @param string $file
	 * @return array
	 * @author Andreas Creten
	 */
	private function get_details($file) {
		$plugin_folder = get_plugins( '/' . plugin_basename(dirname($file)));
		$plugin_file = basename(($file));

		return $plugin_folder[$plugin_file];
	}

	/**
	 * Parse the name of a plugin to a filename, check if it exists
	 *
	 * @param string $name
	 * @param string $exit
	 * @return mixed
	 * @author Andreas Creten
	 */
	private function parse_name($name, $exit = true) {
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
				if($exit) {
					WP_CLI::error('The plugin \''.$name.'\' could not be found.');
					exit();
				}

				return false;
			}
		}

		return $file;
	}

	/**
	 * Check if there is a name set in the arguments, if not show the help function
	 *
	 * @param string $args
	 * @param string $exit
	 * @return void
	 * @author Andreas Creten
	 */
	private function check_name($args, $exit = true) {
		if(empty($args)) {
			WP_CLI::error('Please specify a plugin.');
			WP_CLI::line();
			$this->help();

			if($exit) {
				exit();
			}
		}

		return $args[0];
	}

	/**
	 * Help function for this command
	 *
	 * @param string $args
	 * @return void
	 * @author Andreas Creten
	 */
	public function help($args = array()) {
		// Get the cli arguments
		$arguments = $GLOBALS['argv'];

		// Remove the first entry
		array_shift($arguments);

		// Get the command
		$used_command = array_shift($arguments);

		// Show the list of sub-commands for this command
		WP_CLI::line('Example usage:');

		$methods = WP_CLI_Command::getMethods($this);
		foreach ($methods as $method) {
			if($method != 'help') {
				WP_CLI::line('    wp '.$used_command.' '.$method.' hello-dolly');
			}
			else {
				WP_CLI::line('    wp '.$used_command.' '.$method);
			}
		}
	}
}
