<?php
/**
 * List all contributors to this release.
 *
 * Usage: wp --require=utils/contrib-list.php contrib-list
 *
 * If you run into GitHub API rate limit issues, set a GITHUB_TOKEN
 * environment variable.
 */

use WP_CLI\Utils;

class Contrib_List_Command {

	/**
	 * List all contributors to this release.
	 *
	 * Run within the main WP-CLI project repository.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a specific format.
	 * ---
	 * default: markdown
	 * options:
	 *   - markdown
	 *   - html
	 * ---
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $_, $assoc_args ) {

		$contributors       = array();
		$contributor_count  = 0;
		$pull_request_count = 0;

		// Get the contributors to the current open large project milestones
		foreach ( array( 'wp-cli/wp-cli', 'wp-cli/handbook', 'wp-cli/wp-cli.github.com' ) as $repo ) {
			$milestones = self::get_project_milestones( $repo );
			// Cheap way to get the latest milestone
			$milestone = array_shift( $milestones );
			WP_CLI::log( 'Current open ' . $repo . ' milestone: ' . $milestone->title );
			$pull_requests     = self::get_project_milestone_pull_requests( $repo, $milestone->number );
			$repo_contributors = self::parse_contributors_from_pull_requests( $pull_requests );
			WP_CLI::log( ' - Contributors: ' . count( $repo_contributors ) );
			WP_CLI::log( ' - Pull requests: ' . count( $pull_requests ) );
			$pull_request_count += count( $pull_requests );
			$contributors        = array_merge( $contributors, $repo_contributors );
		}

		// Identify all command dependencies and their contributors
		$milestones = self::get_project_milestones( 'wp-cli/wp-cli', array( 'state' => 'closed' ) );
		// Cheap way to get the latest closed milestone
		$milestone         = array_shift( $milestones );
		$composer_lock_url = sprintf( 'https://raw.githubusercontent.com/wp-cli/wp-cli/v%s/composer.lock', $milestone->title );
		WP_CLI::log( 'Fetching ' . $composer_lock_url );
		$response = Utils\http_request( 'GET', $composer_lock_url );
		if ( 200 !== $response->status_code ) {
			WP_CLI::error( sprintf( 'Could not fetch composer.json (HTTP code %d)', $response->status_code ) );
		}
		$composer_json = json_decode( $response->body, true );
		foreach ( $composer_json['packages'] as $package ) {
			$package_name       = $package['name'];
			$version_constraint = str_replace( 'v', '', $package['version'] );
			if ( ! preg_match( '#^wp-cli/.+-command$#', $package_name ) ) {
				continue;
			}
			// Closed milestones denote a tagged release
			$milestones       = self::get_project_milestones( $package_name, array( 'state' => 'closed' ) );
			$milestone_ids    = array();
			$milestone_titles = array();
			foreach ( $milestones as $milestone ) {
				if ( ! version_compare( $milestone->title, $version_constraint, '>' ) ) {
					continue;
				}
				$milestone_ids[]    = $milestone->number;
				$milestone_titles[] = $milestone->title;
			}
			// No shipped releases for this milestone.
			if ( empty( $milestone_ids ) ) {
				continue;
			}
			WP_CLI::log( 'Closed ' . $package_name . ' milestone(s): ' . implode( ', ', $milestone_titles ) );
			foreach ( $milestone_ids as $milestone_id ) {
				$pull_requests     = self::get_project_milestone_pull_requests( $package_name, $milestone_id );
				$repo_contributors = self::parse_contributors_from_pull_requests( $pull_requests );
				WP_CLI::log( ' - Contributors: ' . count( $repo_contributors ) );
				WP_CLI::log( ' - Pull requests: ' . count( $pull_requests ) );
				$pull_request_count += count( $pull_requests );
				$contributors        = array_merge( $contributors, $repo_contributors );
			}
		}

		WP_CLI::log( 'Total contributors: ' . count( $contributors ) );
		WP_CLI::log( 'Total pull requests: ' . $pull_request_count );

		// Sort and render the contributor list
		asort( $contributors, SORT_NATURAL | SORT_FLAG_CASE );
		if ( in_array( $assoc_args['format'], array( 'markdown', 'html' ), true ) ) {
			$contrib_list = '';
			foreach ( $contributors as $url => $login ) {
				if ( 'markdown' === $assoc_args['format'] ) {
					$contrib_list .= '[' . $login . '](' . $url . '), ';
				} elseif ( 'html' === $assoc_args['format'] ) {
					$contrib_list .= '<a href="' . $url . '">' . $login . '</a>, ';
				}
			}
			$contrib_list = rtrim( $contrib_list, ', ' );
			WP_CLI::log( $contrib_list );
		}
	}

	/**
	 * Get the milestones for a given project
	 *
	 * @param string $project
	 * @return array
	 */
	private static function get_project_milestones( $project, $args = array() ) {
		$request_url            = sprintf( 'https://api.github.com/repos/%s/milestones', $project );
		list( $body, $headers ) = self::make_github_api_request( $request_url, $args );
		return $body;
	}

	/**
	 * Get the pull requests assigned to a milestone of a given project
	 *
	 * @param string $project
	 * @param integer $milestone_id
	 * @return array
	 */
	private static function get_project_milestone_pull_requests( $project, $milestone_id ) {
		$request_url   = sprintf( 'https://api.github.com/repos/%s/issues', $project );
		$args          = array(
			'milestone' => $milestone_id,
			'state'     => 'all',
		);
		$pull_requests = array();
		do {
			list( $body, $headers ) = self::make_github_api_request( $request_url, $args );
			foreach ( $body as $issue ) {
				if ( ! empty( $issue->pull_request ) ) {
					$pull_requests[] = $issue;
				}
			}
			$args        = array();
			$request_url = false;
			// Set $request_url to 'rel="next" if present'
			if ( ! empty( $headers['Link'] ) ) {
				$bits = trim( explode( ',', $headers['Link'] ) );
				foreach ( $bits as $bit ) {
					if ( false !== stripos( $bit, 'rel="next"' ) ) {
						$hrefandrel  = explode( '; ', $bit );
						$request_url = trim( $hrefandrel[0], '<>' );
						break;
					}
				}
			}
		} while ( $request_url );
		return $pull_requests;
	}

	/**
	 * Parse the contributors from pull request objects
	 *
	 * @param array $pull_requests
	 * @return array
	 */
	private static function parse_contributors_from_pull_requests( $pull_requests ) {
		$contributors = array();
		foreach ( $pull_requests as $pull_request ) {
			if ( ! empty( $pull_request->user ) ) {
				$contributors[ $pull_request->user->html_url ] = $pull_request->user->login;
			}
		}
		return $contributors;
	}

	/**
	 * Make a request to the GitHub API
	 *
	 * @param string $url
	 * @param string $args
	 * @return array
	 */
	private static function make_github_api_request( $url, $args = array() ) {
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WP-CLI',
		);
		$token   = getenv( 'GITHUB_TOKEN' );
		if ( false !== $token ) {
			$headers['Authorization'] = 'token ' . $token;
		}
		$response = Utils\http_request( 'GET', $url, $args, $headers );
		if ( 200 !== $response->status_code ) {
			WP_CLI::error( sprintf( 'GitHub API returned: %s (HTTP code %d)', $response->body, $response->status_code ) );
		}
		return array( json_decode( $response->body ), $response->headers );
	}

}

WP_CLI::add_command( 'contrib-list', 'Contrib_List_Command' );
