<?php

namespace JeremyHarris\LazyLoad\TestApp\Model\Table;

use Cake\ORM\Table;

class ArticlesTable extends Table {

	public function initialize(array $config): void {
		parent::initialize($config);
		$this->setPrimaryKey('article_id');

		$this->belongsTo('Author')
			->setClassName('Authors')
			->setForeignKey('author_id');
		$this->belongsTo('Editor')
			->setClassName('Authors')
			->setForeignKey('editor_id');
	}


}
