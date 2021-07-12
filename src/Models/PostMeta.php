<?php

namespace Symlink\ORM\Models;

use Symlink\ORM\Mapping as ORM;
use Symlink\ORM\Models\BaseModel as Model;

/**
 * @ORM_Type Entity
 * @ORM_Table "postmeta"
 * @ORM_AllowSchemaUpdate False
 */
class PostMeta extends Model {

	public $primary_key_name = 'meta_id';

	/**
	 * @ORM_Column_Type bigint
	 * @ORM_Column_Length 20
	 * @ORM_Column_Null NOT NULL
	 * @ORM_ManyToOne \Symlink\ORM\Models\Posts
	 * @ORM_JoinProperty ID
	 */
	protected $post_id;

	/**
	 * @ORM_Column_Type varchar
	 * @ORM_Column_Length 255
	 */
	protected $meta_key;

	/**
	 * @ORM_Column_Type longtext
	 */
	protected $meta_value;

}
