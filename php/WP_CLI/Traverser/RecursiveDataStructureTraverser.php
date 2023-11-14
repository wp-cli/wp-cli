<?php

namespace WP_CLI\Traverser;

use UnexpectedValueException;
use WP_CLI\Exception\NonExistentKeyException;

class RecursiveDataStructureTraverser {

	/**
	 * @var mixed The data to traverse set by reference.
	 */
	protected $data;

	/**
	 * @var null|string The key the data belongs to in the parent's data.
	 */
	protected $key;

	/**
	 * @var null|static The parent instance of the traverser.
	 */
	protected $parent;

	/**
	 * RecursiveDataStructureTraverser constructor.
	 *
	 * @param mixed       $data            The data to read/manipulate by reference.
	 * @param string|int  $key             The key/property the data belongs to.
	 * @param static|null $parent_instance The parent instance of the traverser.
	 */
	public function __construct( &$data, $key = null, $parent_instance = null ) {
		$this->data   =& $data;
		$this->key    = $key;
		$this->parent = $parent_instance;
	}

	/**
	 * Get the nested value at the given key path.
	 *
	 * @param string|int|array $key_path
	 *
	 * @return static
	 */
	public function get( $key_path ) {
		return $this->traverse_to( (array) $key_path )->value();
	}

	/**
	 * Get the current data.
	 *
	 * @return mixed
	 */
	public function value() {
		return $this->data;
	}

	/**
	 * Update a nested value at the given key path.
	 *
	 * @param string|int|array $key_path
	 * @param mixed $value
	 */
	public function update( $key_path, $value ) {
		$this->traverse_to( (array) $key_path )->set_value( $value );
	}

	/**
	 * Update the current data with the given value.
	 *
	 * This will mutate the variable which was passed into the constructor
	 * as the data is set and traversed by reference.
	 *
	 * @param mixed $value
	 */
	public function set_value( $value ) {
		$this->data = $value;
	}

	/**
	 * Unset the value at the given key path.
	 *
	 * @param $key_path
	 */
	public function delete( $key_path ) {
		$this->traverse_to( (array) $key_path )->unset_on_parent();
	}

	/**
	 * Define a nested value while creating keys if they do not exist.
	 *
	 * @param array $key_path
	 * @param mixed $value
	 */
	public function insert( $key_path, $value ) {
		try {
			$this->update( $key_path, $value );
		} catch ( NonExistentKeyException $exception ) {
			$exception->get_traverser()->create_key();
			$this->insert( $key_path, $value );
		}
	}

	/**
	 * Delete the key on the parent's data that references this data.
	 */
	public function unset_on_parent() {
		$this->parent->delete_by_key( $this->key );
	}

	/**
	 * Delete the given key from the data.
	 *
	 * @param $key
	 */
	public function delete_by_key( $key ) {
		if ( is_array( $this->data ) ) {
			unset( $this->data[ $key ] );
		} else {
			unset( $this->data->$key );
		}
	}

	/**
	 * Get an instance of the traverser for the given hierarchical key.
	 *
	 * @param array $key_path Hierarchical key path within the current data to traverse to.
	 *
	 * @throws NonExistentKeyException
	 *
	 * @return static
	 */
	public function traverse_to( array $key_path ) {
		$current = array_shift( $key_path );

		if ( null === $current ) {
			return $this;
		}

		if ( ! $this->exists( $current ) ) {
			$exception = new NonExistentKeyException( "No data exists for key \"{$current}\"" );
			$exception->set_traverser( new static( $this->data, $current, $this->parent ) );
			throw $exception;
		}

		foreach ( $this->data as $key => &$key_data ) {
			if ( $key === $current ) {
				$traverser = new static( $key_data, $key, $this );
				return $traverser->traverse_to( $key_path );
			}
		}
	}

	/**
	 * Create the key on the current data.
	 *
	 * @throws UnexpectedValueException
	 */
	protected function create_key() {
		if ( is_array( $this->data ) ) {
			$this->data[ $this->key ] = null;
		} elseif ( is_object( $this->data ) ) {
			$this->data->{$this->key} = null;
		} else {
			$type = gettype( $this->data );
			throw new UnexpectedValueException(
				"Cannot create key \"{$this->key}\" on data type {$type}"
			);
		}
	}

	/**
	 * Check if the given key exists on the current data.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		return ( is_array( $this->data ) && array_key_exists( $key, $this->data ) ) ||
			( is_object( $this->data ) && property_exists( $this->data, $key ) );
	}
}
