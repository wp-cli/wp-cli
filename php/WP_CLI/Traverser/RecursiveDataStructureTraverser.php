<?php

namespace WP_CLI\Traverser;

use UnexpectedValueException;
use WP_CLI\Exception\NonExistentKeyException;

/**
 * @template TData
 */
class RecursiveDataStructureTraverser {

	/**
	 * The data to traverse set by reference.
	 *
	 * @var TData
	 */
	protected $data;

	/**
	 * @var string|int|null The key the data belongs to in the parent's data.
	 */
	protected $key;

	/**
	 * @var null|self<mixed> The parent instance of the traverser.
	 */
	protected $parent;

	/**
	 * RecursiveDataStructureTraverser constructor.
	 *
	 * @param TData           $data            The data to read/manipulate by reference.
	 * @param string|int|null $key             The key/property the data belongs to.
	 * @param self<mixed>|null $parent_instance The parent instance of the traverser.
	 */
	public function __construct( &$data, $key = null, $parent_instance = null ) {
		$this->data   =& $data;
		$this->key    = $key;
		$this->parent = $parent_instance;
	}

	/**
	 * Get the nested value at the given key path.
	 *
	 * @param string|int|array<string|int> $key_path
	 *
	 * @return mixed
	 */
	public function get( $key_path ) {
		return $this->traverse_to( (array) $key_path )->value();
	}

	/**
	 * Get the current data.
	 *
	 * @return TData
	 */
	public function value() {
		return $this->data;
	}

	/**
	 * Update a nested value at the given key path.
	 *
	 * @param string|int|array<string|int> $key_path
	 * @param mixed $value
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function set_value( $value ) {
		/** @var TData $value - We assume the new value matches the template or the template is mixed */
		$this->data = $value;
	}

	/**
	 * Unset the value at the given key path.
	 *
	 * @param string|int|array<string|int> $key_path
	 *
	 * @return void
	 */
	public function delete( $key_path ) {
		$this->traverse_to( (array) $key_path )->unset_on_parent();
	}

	/**
	 * Define a nested value while creating keys if they do not exist.
	 *
	 * @param array<string|int> $key_path
	 * @param mixed $value
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function unset_on_parent() {
		if ( $this->parent && null !== $this->key ) {
			$this->parent->delete_by_key( $this->key );
		}
	}

	/**
	 * Delete the given key from the data.
	 *
	 * @param string|int $key
	 *
	 * @return void
	 */
	public function delete_by_key( $key ) {
		if ( is_array( $this->data ) ) {
			unset( $this->data[ $key ] );
		} elseif ( is_object( $this->data ) ) {
			unset( $this->data->$key );
		}
	}

	/**
	 * Get an instance of the traverser for the given hierarchical key.
	 *
	 * @param array<string|int> $key_path Hierarchical key path within the current data to traverse to.
	 *
	 * @throws NonExistentKeyException
	 *
	 * @return self<mixed>
	 */
	public function traverse_to( array $key_path ) {
		$current = array_shift( $key_path );

		if ( null === $current ) {
			return $this;
		}

		if ( ! $this->exists( $current ) ) {
			$exception = new NonExistentKeyException( "No data exists for key \"{$current}\"" );
			// When throwing exception, we create a new traverser on the CURRENT level data
			$exception->set_traverser( new self( $this->data, $current, $this->parent ) );
			throw $exception;
		}

		/**
		 * We capture the array by reference.
		 */
		$data = &$this->data;

		if ( is_array( $data ) ) {
			foreach ( $data as $key => &$key_data ) {
				if ( $key === $current ) {
					$traverser = new self( $key_data, $key, $this );
					return $traverser->traverse_to( $key_path );
				}
			}
		} elseif ( is_object( $data ) ) {
			// Objects are passed by identifier, but to maintain the traverser logic
			// specifically for scalar props on objects, we access them directly.
			// Note: Traversing object properties by reference is tricky in PHP loops.
			// We assume standard property access here.
			if ( property_exists( $data, (string) $current ) ) {
				// PHP Objects properties accessed like this are references if the object is passed.
				$traverser = new self( $data->$current, $current, $this );
				return $traverser->traverse_to( $key_path );
			}
		}

		// Should be unreachable due to exists() check, but static analysis likes certainty.
		throw new NonExistentKeyException( 'Key path broken unexpectedly.' );
	}

	/**
	 * Create the key on the current data.
	 *
	 * @throws UnexpectedValueException
	 * @return void
	 */
	protected function create_key() {
		$key = $this->key;
		if ( is_array( $this->data ) && ( is_string( $key ) || is_int( $key ) ) ) {
			$this->data[ $key ] = null;
		} elseif ( is_object( $this->data ) && ( is_string( $key ) || is_int( $key ) ) ) {
			$this->data->{$key} = null;
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
	 * @param string|int $key
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		return ( is_array( $this->data ) && array_key_exists( $key, $this->data ) ) ||
			( is_object( $this->data ) && property_exists( $this->data, (string) $key ) );
	}
}
