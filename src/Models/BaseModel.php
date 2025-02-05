<?php

namespace Symlink\ORM\Models;

use Symlink\ORM\Manager;
use Symlink\ORM\Mapping;

abstract class BaseModel {

	/**
	 * Every model has an ID.
	 * @var
	 */
	protected $ID;

	/**
	 * Default Primary Key Name (ID)
	 * @var string
	 */
	public $primary_key_name = 'ID';

	/**
	 * @var
	 */
	private $hash;

	/**
	 * BaseModel constructor.
	 */
	public function __construct() {
		$this->hash = spl_object_hash( $this );
	}

	/**
	 * Perform a manual clone of this object.
	 */
	public function __clone() {
		$class_name = get_class( $this );
		$object     = new $class_name;
		$schema     = Mapping::getMapper()->getProcessed( $class_name )['schema'];

		foreach ( array_keys( $schema ) as $property ) {
			$object->set( $property, $this->get( $property ) );
		}
	}

	/**
	 * Get Primary key name
	 *
	 * @return string
	 */
	public function getPrimaryKeyName() {
		return $this->primary_key_name;
	}

	/**
	 * Get ID (Primary key)
	 *
	 * @return string
	 */
	public function getId() {
		return $this->{$this->primary_key_name};
	}

	/**
	 * Set ID
	 *
	 * @param int $id
	 *
	 * @return int
	 */
	public function setId( int $id ): int {
		return $this->{$this->primary_key_name} = $id;
	}

	/**
	 * @return string
	 */
	public function getHash() {
		return $this->hash;
	}

	/**
	 * @return mixed
	 */
	public function getTableName() {
		return Mapping::getMapper()->getProcessed( get_class( $this ) )['ORM_Table'];
	}

	/**
	 * @return mixed
	 */
	public function getSchema() {
		return Mapping::getMapper()->getProcessed( get_class( $this ) )['schema'];
	}

	/**
	 * @return mixed
	 */
	public function getPlaceholders() {
		return Mapping::getMapper()->getProcessed( get_class( $this ) )['placeholder'];
	}

	/**
	 * Return keyed values from this object as per the schema (no ID).
	 * @return array
	 */
	public function getAllValues() {
		$values = [];
		foreach ( array_keys( $this->getSchema() ) as $property ) {
			$values[ $property ] = $this->get( $property );
		}

		return $values;
	}

	/**
	 * Return unkeyed values from this object as per the schema (no ID).
	 * @return bool
	 */
	public function getAllUnkeyedValues() {

		$values = array_map( function ( $key ) {

			$value = $this->get( $key );

			// If this is an Object, get the primary key to save
			// To avoid "Notice: wpdb::prepare was called incorrectly. Unsupported value type (object). Please see Debugging in WordPress for more information. (This message was added in version 4.8.2.) in /var/www/superteamseo.com/wp-includes/functions.php on line 5535"
			if ( is_object( $value ) ) {
				$value = $value->{$value->primary_key_name};
			}

			return $value;
		}, array_keys( $this->getSchema() ) );


		return $values;
	}

	/**
	 * Get the raw, underlying value of a property (don't perform a JOIN or lazy
	 * loaded database query).
	 *
	 * @param $property
	 *
	 * @return mixed
	 * @throws \Symlink\ORM\Exceptions\PropertyDoesNotExistException
	 */
	final public function getDBValue( $property ) {
		return $this->get( $property );
	}

	/**
	 * Generic getter.
	 *
	 * @param $property
	 *
	 * @return mixed
	 * @throws \Symlink\ORM\Exceptions\PropertyDoesNotExistException
	 */
	final public function get( $property ) {
		// Check to see if the property exists on the model.
		if ( ! property_exists( $this, $property ) ) {
			throw new \Symlink\ORM\Exceptions\PropertyDoesNotExistException( sprintf( __( 'The property %s does not exist on the model %s.' ), $property, get_class( $this ) ) );
		}

		// If this property is a ManyToOne, check to see if it's an object and lazy
		// load it if not.
		$many_to_one_class = Mapping::getMapper()->getPropertyAnnotationValue( get_class( $this ), $property, 'ORM_ManyToOne' );
		/** @var string $many_to_one_property */
		$many_to_one_property = Mapping::getMapper()->getPropertyAnnotationValue( get_class( $this ), $property, 'ORM_JoinProperty' );

		if ( $many_to_one_class && $many_to_one_property && ! is_object( $this->$property ) ) {
			// Lazy load.
			$orm               = Manager::getManager();
			$object_repository = $orm->getRepository( $many_to_one_class );

			$object = $object_repository->findBy( [ $many_to_one_property => $this->$property ] );

			if ( $object ) {
				$this->$property = $object;
			}
		}

		// Return the value of the field.
		return $this->$property;
	}

	/**
	 * Get multiple values from this object given an array of properties.
	 *
	 * @param $columns
	 *
	 * @return array
	 */
	final public function getMultiple( $columns ) {
		$results = [];

		if ( is_array( $columns ) ) {
			foreach ( $columns as $column ) {
				$results[ $column ] = $this->get( $column );
			}
		}

		return $results;
	}

	/**
	 * Generic setter.
	 *
	 * @param $column
	 * @param $value
	 *
	 * @return bool
	 * @throws \Symlink\ORM\Exceptions\PropertyDoesNotExistException
	 */
	final public function set( $column, $value ) {

		// Specific to ID (Primary key)
		if ( $column === $this->primary_key_name ) {
			$this->setId( $value );
		} else {
			// Check to see if the property exists on the model.
			if ( ! property_exists( $this, $column ) ) {
				throw new \Symlink\ORM\Exceptions\PropertyDoesNotExistException( sprintf( __( 'The property %s does not exist on the model %s.' ), $column, get_class( $this ) ) );
			}

			// Update the model with the value.
			$this->$column = $value;
		}

		return true;
	}

}
