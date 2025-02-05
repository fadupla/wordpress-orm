<?php

namespace Symlink\ORM\Repositories;

use Symlink\ORM\QueryBuilder;

class BaseRepository {

	private $classname;

	private $annotations;

	private $primary_key_name;

	/**
	 * BaseRepository constructor.
	 *
	 * @param $classname
	 * @param $annotations
	 */
	public function __construct( $classname, $annotations ) {
		$this->classname        = $classname;
		$this->annotations      = $annotations;
		$this->primary_key_name = ( new $classname )->getPrimaryKeyName();
	}

	/**
	 * @param $classname
	 * @param $annotations
	 *
	 * @return \Symlink\ORM\Repositories\BaseRepository
	 */
	public static function getInstance( $classname, $annotations ) {
		// Get the class (as this could be a child of BaseRepository)
		$this_repository_class = get_called_class();


		// Return a new instance of the class.
		return new $this_repository_class( $classname, $annotations );
	}

	/**
	 * @return \Symlink\ORM\QueryBuilder
	 */
	public function createQueryBuilder() {
		return new QueryBuilder( $this );
	}

	/**
	 * Getter used in the query builder.
	 * @return mixed
	 */
	public function getObjectClass() {
		return $this->classname;
	}

	/**
	 * Getter used in the query builder
	 * @return mixed
	 */
	public function getDBTable() {
		return $this->annotations['ORM_Table'];
	}

	/**
	 * Getter used in the query builder.
	 * @return array
	 */
	public function getObjectProperties() {
		return array_merge(
			[ $this->primary_key_name ],
			array_keys( $this->annotations['schema'] )
		);
	}

	/**
	 * Getter used in the query builder.
	 * @return array
	 */
	public function getObjectPropertyPlaceholders() {
		return array_merge(
			[ $this->primary_key_name => '%d' ],
			$this->annotations['placeholder']
		);
	}

	/**
	 * Find a single object by ID.
	 *
	 * @param $id
	 *
	 * @return array|bool|mixed
	 */
	public function find( $id ) {

		return $this->createQueryBuilder()
		            ->where( $this->primary_key_name, $id, '=' )
		            ->orderBy( $this->primary_key_name, 'ASC' )
		            ->buildQuery()
		            ->getResults();
	}

	/**
	 * Return all objects of this type.
	 *
	 * @return array|bool|mixed
	 */
	public function findAll() {
		return $this->createQueryBuilder()
		            ->orderBy( $this->primary_key_name, 'ASC' )
		            ->buildQuery()
		            ->getResults();
	}

	/**
	 * Returns all objects with matching property value.
	 *
	 * @param array $criteria
	 *
	 * @return array
	 */
	public function findBy( array $criteria ) {
		$qb = $this->createQueryBuilder();
		foreach ( $criteria as $property => $value ) {
			$qb->where( $property, $value, '=' );
		}

		return $qb->orderBy( $this->primary_key_name, 'ASC' )
		          ->buildQuery()
		          ->getResults();
	}

}
