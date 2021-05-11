<?php

namespace WP_CLI\Fetchers;

use WP_CLI;
use WP_CLI\ExitException;

/**
 * Fetch a WordPress entity for use in a subcommand.
 */
abstract class Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg;

	/**
	 * @param string $arg The raw CLI argument.
	 * @return mixed|false The item if found; false otherwise.
	 */
	abstract public function get( $arg );

	/**
	 * Like get(), but calls WP_CLI::error() instead of returning false.
	 *
	 * @param string $arg The raw CLI argument.
	 * @return mixed The item if found.
	 * @throws ExitException If the item is not found.
	 */
	public function get_check( $arg ) {
		$item = $this->get( $arg );

		if ( ! $item ) {
			WP_CLI::error( sprintf( $this->msg, $arg ) );
		}

		return $item;
	}

	/**
	 * Get multiple items.
	 *
	 * @param array $args The raw CLI arguments.
	 * @return array The list of found items.
	 */
	public function get_many( $args ) {
		$items = [];

		foreach ( $args as $arg ) {
			$item = $this->get( $arg );

			if ( $item ) {
				$items[] = $item;
			} else {
				WP_CLI::warning( sprintf( $this->msg, $arg ) );
			}
		}

		return $items;
	}
}

