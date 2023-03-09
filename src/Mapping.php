<?php

namespace Symlink\ORM;

use Minime\Annotations\Interfaces\AnnotationsBagInterface;
use Minime\Annotations\Reader;
use Minime\Annotations\AnnotationsBag;

class Mapping {

	/**
	 * @var \Symlink\ORM\Manager
	 */
	private static $mapping_service = null;

	/**
	 * @var \Minime\Annotations\Reader
	 */
	private $reader;

	/**
	 * The processed models.
	 *
	 * @var array
	 */
	private $models;

	/**
	 * @type string[]
	 */
	private $model_property_index_types = [
		'index',
		'unique',
		'spatial',
		'fulltext',
	];

	/**
	 * Initializes a non static copy of itself when called. Subsequent calls
	 * return the same object (fake dependency injection/service).
	 *
	 * @return \Symlink\ORM\Mapping
	 */
	public static function getMapper() {
		// Initialize the service if it's not already set.
		if ( self::$mapping_service === null ) {
			self::$mapping_service = new Mapping();
		}

		// Return the instance.
		return self::$mapping_service;
	}

	/**
	 * Returns an instance of the annotation reader (caches within this request).
	 *
	 * @return \Minime\Annotations\Interfaces\ReaderInterface|\Minime\Annotations\Reader
	 */
	private function getReader() {
		// If the annotation reader isn't set, create it.
		if ( ! $this->reader ) {
			$this->reader = Reader::createFromDefaults();
		}

		return $this->reader;
	}

	/**
	 * @param $classname
	 * @param $property
	 * @param $key
	 *
	 * @return AnnotationsBagInterface
	 */
	public function getPropertyAnnotationValue( $classname, $property, $key ) {
		// Get the annotations.
		$annotations = $this->getReader()->getPropertyAnnotations( $classname, $property );

		// Return the key we want from this list of property annotations.
		return $annotations->get( $key );
	}

	/**
	 * Process the class annotations, adding an entry the $this->models array.
	 *
	 * @param $classname
	 *
	 * @return mixed
	 * @throws \Symlink\ORM\Exceptions\RepositoryClassNotDefinedException
	 * @throws \Symlink\ORM\Exceptions\RequiredAnnotationMissingException
	 * @throws \Symlink\ORM\Exceptions\UnknownColumnTypeException
	 */
	public function getProcessed( $classname ) {

		if ( ! isset( $this->models[ $classname ] ) ) {
			// Get the annotation reader instance.
			$class_annotations = $this->getReader()->getClassAnnotations( $classname );

			// Validate @ORM_Type
			if ( ! $class_annotations->get( 'ORM_Type' ) ) {
				$this->models[ $classname ]['validated'] = false;

				throw new \Symlink\ORM\Exceptions\RequiredAnnotationMissingException( sprintf( __( 'The annotation ORM_Type does not exist on the model %s.' ), $classname ) );
			} else {
				$this->models[ $classname ]['ORM_Type'] = $class_annotations->get( 'ORM_Type' );
			}

			// Validate @ORM_Table
			if ( ! $class_annotations->get( 'ORM_Table' ) ) {
				$this->models[ $classname ]['validated'] = false;
				throw new \Symlink\ORM\Exceptions\RequiredAnnotationMissingException( sprintf( __( 'The annotation ORM_Table does not exist on the model %s.' ), $classname ) );
			} else {
				$this->models[ $classname ]['ORM_Table'] = $class_annotations->get( 'ORM_Table' );
			}

			// Validate @ORM_AllowSchemaUpdate
			if ( ! $class_annotations->get( 'ORM_AllowSchemaUpdate' ) ) {
				$this->models[ $classname ]['validated'] = false;
				throw new \Symlink\ORM\Exceptions\RequiredAnnotationMissingException( sprintf( __( 'The annotation ORM_AllowSchemaUpdate does not exist on the model.' ), $classname ) );
			} else {
				$this->models[ $classname ]['ORM_AllowSchemaUpdate'] = filter_var( $class_annotations->get( 'ORM_AllowSchemaUpdate' ), FILTER_VALIDATE_BOOLEAN );;
			}

			// Validate @ORM_Repository
			if ( $class_annotations->get( 'ORM_Repository' ) ) {
				if ( ! class_exists( $class_annotations->get( 'ORM_Repository' ) ) ) {
					throw new \Symlink\ORM\Exceptions\RepositoryClassNotDefinedException( sprintf( __( 'Repository class %s does not exist on model %s.' ), $class_annotations->get( 'ORM_Repository' ), $classname ) );
				} else {
					$this->models[ $classname ]['ORM_Repository'] = $class_annotations->get( 'ORM_Repository' );
				}
			}

			// Check the property annotations.
			$reflection_class = new \ReflectionClass( $classname );

			// Start with blank schema.
			$this->models[ $classname ]['schema'] = [];

			// Loop through the class properties.
			foreach ( $reflection_class->getProperties() as $property ) {

				// Get the annotation of this property.
				$property_annotation = $this->getReader()
				                            ->getPropertyAnnotations( $classname, $property->name );

				// Silently ignore properties that do not have the ORM_Column_Type annotation.
				if ( $property_annotation->get( 'ORM_Column_Type' ) ) {

					$column_type = strtolower( $property_annotation->get( 'ORM_Column_Type' ) );

					// Test the ORM_Column_Type
					if ( ! in_array( $column_type, [
						'datetime',
						'tinyint',
						'smallint',
						'int',
						'bigint',
						'char',
						'varchar',
						'tinytext',
						'text',
						'mediumtext',
						'longtext',
						'float',
					] )
					) {
						throw new \Symlink\ORM\Exceptions\UnknownColumnTypeException( sprintf( __( 'Unknown model property column type "%s" set in @ORM_Column_Type on model %s..' ), $column_type, $classname ) );
					}

					// Build the rest of the schema partial.
					$schema_string = $property->name . ' ' . $column_type;

					if ( $property_annotation->get( 'ORM_Column_Length' ) ) {
						$schema_string .= '(' . $property_annotation->get( 'ORM_Column_Length' ) . ')';
					}

					if ( $property_annotation->get( 'ORM_Column_Null' ) ) {
						$schema_string .= ' ' . $property_annotation->get( 'ORM_Column_Null' );
					}

					// Add the schema to the mapper array for this class.
					$placeholder_values_type = '%s';  // Initially assume column is string type.

					if ( in_array( $column_type, [  // If the column is a decimal type.
						'tinyint',
						'smallint',
						'bigint',
					] ) ) {
						$placeholder_values_type = '%d';
					}

					if ( in_array( $column_type, [  // If the column is a float type.
						'float',
					] ) ) {
						$placeholder_values_type = '%f';
					}

					// Add the comment for this class
					$comment = trim( $property_annotation->get( 'ORM_Comment' ) );
					$comment = str_replace( [ "'", '"' ], '', $comment );
					if ( $comment ) {
						$schema_string .= " COMMENT '" . $comment . "'";
					}

					$this->models[ $classname ]['schema'][ $property->name ]      = $schema_string;
					$this->models[ $classname ]['placeholder'][ $property->name ] = $placeholder_values_type;

				}

				/**
				 * Index
				 */
				if ( $property_annotation->get( 'ORM_Index_Type' ) ) {

					$index_type = $property_annotation->get( 'ORM_Index_Type' );

					// Test the ORM_Index_Type
					if ( ! in_array( $index_type, $this->model_property_index_types ) ) {
						throw new \Symlink\ORM\Exceptions\UnknownIndexTypeException( sprintf( __( 'Unknown model property index type "%s" set in @ORM_Column_Type on model %s. It should be one of: "%s".' ), $index_type, $classname, implode( '","', $this->model_property_index_types ) ) );
					}

					// Index column(s)
					if ( $property_annotation->get( 'ORM_Index_Columns' ) ) {
						$columns_string = $property_annotation->get( 'ORM_Index_Columns' );
					} else {
						$columns_string = $property->name;
					}

					// Index name
					if ( $property_annotation->get( 'ORM_Index_Name' ) ) {
						$index_name = $property_annotation->get( 'ORM_Index_Name' );
					} else {
						// Build an index name with index column(s)
						$build_index_name = str_replace( ' ', '', trim( $columns_string ) );
						$build_index_name = str_replace( ',', '_', $build_index_name );
						$index_name       = $build_index_name;
					}

					$index_string = sprintf( "ADD %s `%s` (%s)", $index_type, $index_name, $columns_string );

					$this->models[ $classname ]['index'][ $property->name ] = $index_string;
				}

			}


		}

		// Return the processed annotations.
		return $this->models[ $classname ];
	}

	/**
	 * Compares a database table schema to the model schema (as defined in the
	 * annotations). If there any differences, the database schema is modified to
	 * match the model.
	 *
	 * @param string $classname
	 *
	 * @return array
	 *
	 * @throws Exceptions\AllowSchemaUpdateIsFalseException
	 * @throws Exceptions\RepositoryClassNotDefinedException
	 * @throws Exceptions\RequiredAnnotationMissingException
	 * @throws Exceptions\UnknownColumnTypeException
	 */
	public function updateSchema( string $classname ): array {
		global $wpdb;

		// Get the model annotation data.
		$mapped = $this->getProcessed( $classname );

		// Are we allowed to update the schema of this model in the db?
		if ( ! $mapped['ORM_AllowSchemaUpdate'] ) {
			throw new \Symlink\ORM\Exceptions\AllowSchemaUpdateIsFalseException( sprintf( __( 'Cannot update model schema %s. ORM_AllowSchemaUpdate is set to FALSE.' ), $classname ) );
		}

		// Create an ID type string.
		$primary_key_name = ( new $classname )->getPrimaryKeyName();
		$id_type          = $primary_key_name;
		$id_type_string   = $primary_key_name . ' bigint(20) NOT NULL AUTO_INCREMENT';

		// Build the SQL CREATE TABLE command for use with dbDelta.
		$table_name = $wpdb->prefix . $mapped['ORM_Table'];

		$charset_collate = $wpdb->get_charset_collate();

		$sql = sprintf( "CREATE TABLE %s ( %s, %s,\nPRIMARY KEY (%s) )\n%s;", $table_name, $id_type_string, implode( ",\n  ", $mapped['schema'] ), $id_type, $charset_collate );

		// Use dbDelta to do all the hard work.
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		return dbDelta( $sql );
	}


	/**
	 * Add indexes of model. Indexes can be reset on an update for example
	 *
	 * @param string $classname
	 * @param bool   $reset_indexes
	 *
	 * @return array
	 * @throws Exceptions\AllowSchemaUpdateIsFalseException
	 * @throws Exceptions\RepositoryClassNotDefinedException
	 * @throws Exceptions\RequiredAnnotationMissingException
	 * @throws Exceptions\UnknownColumnTypeException
	 */
	public function updateIndexes( string $classname, bool $reset_indexes ): array {

		global $wpdb;

		// Init
		$messages = [];

		// Get the model annotation data.
		$mapped = $this->getProcessed( $classname );

		// Get table name
		$table_name = $wpdb->prefix . $mapped['ORM_Table'];

		// Are we allowed to update the schema of this model in the db?
		if ( ! $mapped['ORM_AllowSchemaUpdate'] ) {
			throw new \Symlink\ORM\Exceptions\AllowSchemaUpdateIsFalseException( sprintf( __( 'Cannot update model schema %s. ORM_AllowSchemaUpdate is set to FALSE.' ), $classname ) );
		}

		if ( $reset_indexes ) {
			// Drop previous indexes
			$sql_drops = [];
			$results   = $wpdb->get_results( sprintf( "SHOW INDEX FROM `%s`", $table_name ) );
			foreach ( $results as $result ) {
				$index_name = $result->Key_name;

				if ( $index_name !== 'PRIMARY' ) {
					$sql_drops[] = sprintf( "DROP INDEX `%s`", $index_name );
				}
			}
			$query_result = $wpdb->query( sprintf( "ALTER TABLE `%s` %s", $table_name, implode( ",\n", $sql_drops ) ) );
			$messages[]   = ( $query_result ) ? __( 'Indexes were dropped' ) : __( 'Indexes could not be dropped' );
		}

		// Any indexes to add?
		if ( isset( $mapped['index'] ) ) {

			// Build the SQL ALTER TABLE command
			$sql = sprintf( "ALTER TABLE %s %s;", $table_name, implode( ",\n", $mapped['index'] ) );

			$query_result = $wpdb->query( $sql );
			if ( $query_result ) {
				$messages[] = sprintf( _n( '%s indexe was added to table %s', '%s indexes were added to table %s', count( $mapped['index'] ) ), count( $mapped['index'] ), $table_name );
			} else {
				$messages[] = __( 'Indexes could not be updated' );
			}

		} else {
			$messages[] = __( 'No indexes to update' );
		}

		return $messages;
	}

	/**
	 * Truncate a Model table
	 *
	 * @param string $classname
	 *
	 * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
	 *                  affected/selected for all other queries. Boolean false on error.
	 *
	 * @throws Exceptions\RepositoryClassNotDefinedException
	 * @throws Exceptions\RequiredAnnotationMissingException
	 * @throws Exceptions\UnknownColumnTypeException
	 * @throws Exceptions\AllowSchemaUpdateIsFalseException
	 */
	public function truncateTable( string $classname ): int|bool {
		global $wpdb;

		// Get the model annotation data.
		$mapped = $this->getProcessed( $classname );

		// Are we allowed to update the schema of this model in the db?
		if ( ! $mapped['ORM_AllowSchemaUpdate'] ) {
			throw new \Symlink\ORM\Exceptions\AllowSchemaUpdateIsFalseException( sprintf( __( 'Refused to drop table for model %s. ORM_AllowSchemaUpdate is FALSE.' ), $classname ) );
		}

		// Drop the table.
		$table_name = $wpdb->prefix . $mapped['ORM_Table'];
		$sql        = "TRUNCATE " . $table_name;

		return $wpdb->query( $sql );
	}

	/**
	 * Drop a Model table
	 *
	 * @param string $classname
	 *
	 * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
	 *                  affected/selected for all other queries. Boolean false on error.
	 *
	 * @throws Exceptions\RepositoryClassNotDefinedException
	 * @throws Exceptions\RequiredAnnotationMissingException
	 * @throws Exceptions\UnknownColumnTypeException
	 * @throws Exceptions\AllowSchemaUpdateIsFalseException
	 */
	public function dropTable( string $classname ): int|bool {
		global $wpdb;

		// Get the model annotation data.
		$mapped = $this->getProcessed( $classname );

		// Are we allowed to update the schema of this model in the db?
		if ( ! $mapped['ORM_AllowSchemaUpdate'] ) {
			throw new \Symlink\ORM\Exceptions\AllowSchemaUpdateIsFalseException( sprintf( __( 'Refused to drop table for model %s. ORM_AllowSchemaUpdate is FALSE.' ), $classname ) );
		}

		// Drop the table.
		$table_name = $wpdb->prefix . $mapped['ORM_Table'];
		$sql        = "DROP TABLE IF EXISTS " . $table_name;

		return $wpdb->query( $sql );
	}

}
