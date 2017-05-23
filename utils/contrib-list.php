<?php
/**
 * List all contributors to this release.
 *
 * Usage: wp --require=utils/contrib-list.php contrib-list
 */

use WP_CLI\Utils;

class Contrib_List_Command {

	/**
	 * List all contributors to this release.
	 *
	 * Run within the main WP-CLI project repository.
	 *
	 * @when before_wp_load
	 */
	public function __invoke() {

		$contributors = array();

		// Get the contributors to the current open wp-cli/wp-cli milestone
		$milestones = self::get_project_milestones( 'wp-cli/wp-cli' );
		$milestone = array_shift( $milestones );
		$pull_requests = self::get_project_milestone_pull_requests( 'wp-cli/wp-cli', $milestone->number );
		$contributors = array_merge( $contributors, self::parse_contributors_from_pull_requests( $pull_requests ) );

		// @todo Identify all command dependencies and their contributors

		asort( $contributors, SORT_NATURAL | SORT_FLAG_CASE );
		$contrib_list = '';
		foreach( $contributors as $url => $login ) {
			$contrib_list .= '[' . $login . '](' . $url . '), ';
		}
		$contrib_list = rtrim( $contrib_list, ', ' );
		WP_CLI::log( $contrib_list );
	}

	/**
	 * Get the milestones for a given project
	 *
	 * @param string $project
	 * @return array
	 */
	private static function get_project_milestones( $project ) {
		$request_url = sprintf( 'https://api.github.com/repos/%s/milestones', $project );
		list( $body, $headers ) = self::make_github_api_request( $request_url );
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
		$request_url = sprintf( 'https://api.github.com/repos/%s/issues', $project );
		$args = array(
			'milestone' => $milestone_id,
			'state'     => 'all',
		);
		$pull_requests = array();
		do {
			list( $body, $headers ) = self::make_github_api_request( $request_url, $args );
			foreach( $body as $issue ) {
				if ( ! empty( $issue->pull_request ) ) {
					$pull_requests[] = $issue;
				}
			}
			$args = array();
			$request_url = false;
			// Set $request_url to 'rel="next" if present'
			if ( ! empty( $headers['Link'] ) ) {
				$bits = explode( ',', $headers['Link'] );
				foreach( $bits as $bit ) {
					if ( false !== stripos( $bit, 'rel="next"' ) ) {
						$hrefandrel = explode( '; ', $bit );
						$request_url = trim( $hrefandrel[0], '<>' );
						break;
					}
				}
			}
		} while( $request_url );
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
		foreach( $pull_requests as $pull_request ) {
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
		if ( $token = getenv( 'GITHUB_TOKEN' ) ) {
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
