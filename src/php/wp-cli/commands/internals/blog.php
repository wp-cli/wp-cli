<?php

WP_CLI::addCommand('blog', 'BlogCommand');

/**
 * Implement core command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class BlogCommand extends WP_CLI_Command {
	
	private function _create_usage() {
		WP_CLI::line( "usage: wp blog create <base> <title> [email] [site_id] [public=(1 or 0)] []" );
	}
	
	private function get_site($site_id) {
		global $wpdb;
		// Load site data
		$sites = $wpdb->get_results("SELECT * FROM $wpdb->site WHERE `id` = ".$site_id);
		if (count($sites) > 0) {
			// Only care about domain and path which are set here
			return $sites[0];
		}
		
		return false;
	}
	
	public function create($args) {
		if (!is_multisite()) {
			WP_CLI::line("ERROR: not a multisite instance");
			exit;
		}
		global $wpdb;
		
		// domain required
		// title required
		// email optional
		// site optional
		// public optional
		if (empty($args[0]) || empty($args[1])) {
			$this->_create_usage();
			exit;
		}
		
		$base = $args[0];
		$title = $args[1];
		$email = empty($args[2]) ? '' : $args[2];
		// Site
		if (!empty($args[3])) {
			$site = $this->get_site($args[3]);
			if ($site === false) {
				WP_CLI::line('ERROR: Site with id '.$args[3].'does not exist');
				exit;
			}
		}
		else {
			$site = wpmu_current_site();
		}
		// Public
		if (!empty($args[4])) {
			$public = $args[4];
			// Check for 1 or 0
			if ($public != '1' && $public != '0') {
				$this->_create_usage();
			}
		}
		else {
			$public = 1;
		}

		$domain = '';
		if (preg_match( '|^([a-zA-Z0-9-])+$|', $blog['domain'])) {
			$domain = strtolower( $blog['domain'] );
		}
		
		// If not a subdomain install, make sure the domain isn't a reserved word
		if (!is_subdomain_install()) {
			$subdirectory_reserved_names = apply_filters('subdirectory_reserved_names', array( 'page', 'comments', 'blog', 'files', 'feed' ));
			if (in_array($domain, $subdirectory_reserved_names)) {
				WP_CLI::line(sprintf(__('ERROR: The following words are reserved for use by WordPress functions and cannot be used as blog names: <code>%s</code>'), implode('</code>, <code>', $subdirectory_reserved_names)));
				exit;
			}
		}
		

		// Check for valid email, if not, use the first Super Admin found
		// Probably a more efficient way to do this so we dont query for the
		// User twice if super admin
		$email = sanitize_email($email);
		if (empty($email) || !is_email($email)) {
			//@TODO just use super admin email if not specified
			$super_admins = get_super_admins();

			$email = '';
			if (!empty($super_admins) && is_array($super_admins)) {
				// Just get the first one
				$super_login = $super_admins[0];
				$super_user = get_user_by('login', $super_login);
				if ($super_user) {
					$email = $super_user->user_email;
				}
			}
		}

		if ( is_subdomain_install() ) {
			$newdomain = $base . '.' . preg_replace( '|^www\.|', '', $site->domain );
			$path = $site->path;
			$url = $newdomain;
		} else {
			$newdomain = $site->domain;
			$path = $site->domain.$site->path.$base.'/';
			$url = $path;
		}
		
		$password = 'N/A';
		$user_id = email_exists($email);
		if (!$user_id) { // Create a new user with a random password
			$password = wp_generate_password(12, false);
			$user_id = wpmu_create_user($base, $password, $email);
			if (false == $user_id ) {
				WP_CLI::line('ERROR: There was an issue creating the user.');
				exit;
			}
			else {
				wp_new_user_notification($user_id, $password);
			}
		}
		
		$wpdb->hide_errors();
		$id = wpmu_create_blog($newdomain, $path, $title, $user_id, array( 'public' => $public ), $site->id);
		$wpdb->show_errors();
		if (!is_wp_error($id)) {
			if ( !is_super_admin($user_id) && !get_user_option('primary_blog', $user_id)) {
				update_user_option($user_id, 'primary_blog', $id, true);
			}
//			$content_mail = sprintf(__( "New site created by WP Command Line Interface\n\nAddress: %2s\nName: %3s"), get_site_url($id), stripslashes($title));
			//@TODO Current site
//			wp_mail(get_site_option('admin_email'), sprintf(__('[%s] New Site Created'), $current_site->site_name), $content_mail, 'From: "Site Admin" <'.get_site_option( 'admin_email').'>');
		} 
		else {
			WP_CLI::line($id->get_error_message());
			exit;
		}	
		WP_CLI::line('Blog created with URL: '.$url);
	}
		
	public function update($args) {}
		
	public function delete($args) {}
		
	public function help() {
		WP_CLI::line( <<<EOB
usage: wp blog <sub-command> [<theme-name>]

Available sub-commands:
   create   display status of all installed themes or of a particular theme

   update   activate a particular theme

   delete   print path to the theme's stylesheet
      --dir   get the path to the closest parent directory
EOB
		);
	}
}