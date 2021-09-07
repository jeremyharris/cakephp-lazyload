<?php
namespace JeremyHarris\LazyLoad\TestApp\Model\Table;

use Cake\ORM\Table;

class ArticlesTable extends Table
{
	
	public function initialize(array $config): void {
		parent::initialize($config);
		$this->belongsTo('Author', [
			'className' => 'Authors', 
			'foreignKey' => 'author_id'
		]);
		$this->belongsTo('Editor', [
			'className' => 'Authors',
			'foreignKey' => 'editor_id'
		]);
	}


}
