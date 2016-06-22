<?php
namespace JeremyHarris\LazyLoad\Test\TestCase\ORM;

use Cake\Event\Event;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use JeremyHarris\LazyLoad\TestApp\Model\Entity\Comment;
use JeremyHarris\LazyLoad\TestApp\Model\Entity\TablelessEntity;

/**
 * LazyLoadEntityTrait test
 */
class LazyLoadEntityTraitTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.JeremyHarris\LazyLoad.articles',
        'plugin.JeremyHarris\LazyLoad.articles_tags',
        'plugin.JeremyHarris\LazyLoad.authors',
        'plugin.JeremyHarris\LazyLoad.comments',
        'plugin.JeremyHarris\LazyLoad.tags',
        'plugin.JeremyHarris\LazyLoad.users',
    ];

    /**
     * setUp
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Articles = TableRegistry::get('Articles');
        $this->Articles->entityClass('\JeremyHarris\LazyLoad\TestApp\Model\Entity\LazyLoadableEntity');
        $this->Articles->belongsTo('Authors');
        $this->Articles->hasMany('Comments');
        $this->Articles->belongsToMany('Tags', [
            'joinTable' => 'articles_tags',
        ]);
    }

    /**
     * tests that trying to lazy load from a new entity doesn't throw errors
     *
     * @return void
     */
    public function testMissingPrimaryKey()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = new Comment(['user_id' => 2]);
        $this->assertNull($comment->author);
    }

    /**
     * tests that we can override _repository to prevent errors from being thrown
     * in cases where we're creating an entity without a table. this happens in
     * tests sometimes
     *
     * @return void
     * @see README.md#testing
     */
    public function testTablelessEntity()
    {
        $entity = new TablelessEntity();
        $this->assertNull($entity->missing_property);
    }

    /**
     * tests that we only call the has() method once
     *
     * @return void
     */
    public function testParentHasAccessedOnce()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->getMock(
            '\JeremyHarris\LazyLoad\TestApp\Model\Entity\Comment',
            ['_parentHas', '_repository'],
            [['id' => 1, 'user_id' => 2]]
        );
        $comment
            ->expects($this->once())
            ->method('_repository')
            ->will($this->returnValue($this->Comments));
        $comment
            ->expects($this->once())
            ->method('_parentHas')
            ->will($this->returnValue(false));

        $this->assertTrue($comment->has('author'));
    }

    /**
     * tests that we only call the __get() magic method once
     *
     * @return void
     */
    public function testParentGetAccessedOnce()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->getMock(
            '\JeremyHarris\LazyLoad\TestApp\Model\Entity\Comment',
            ['_parentGet', '_repository'],
            [['id' => 1, 'user_id' => 2]]
        );
        $comment
            ->expects($this->once())
            ->method('_repository')
            ->will($this->returnValue($this->Comments));
        $comment
            ->expects($this->once())
            ->method('_parentGet')
            ->will($this->returnValue(null));

        $author = $comment->author;

        $this->assertEquals(2, $author->id);
    }

    /**
     * tests that lazyload doesn't interfere with existing accessor methods
     *
     * @return void
     */
    public function testGetAccessor()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->entityClass('\JeremyHarris\LazyLoad\TestApp\Model\Entity\Comment');
        $comment = $this->Comments->get(1);

        $this->assertEquals('accessor', $comment->accessor);
    }

    /**
     * tests get() when property isn't associated
     *
     * @return void
     */
    public function testGet()
    {
        $article = $this->Articles->get(1);

        $this->assertNull($article->not_associated);
    }

    /**
     * tests cases where `source()` is empty, caused when an entity is manually
     * created
     *
     * @return void
     */
    public function testEmptySource()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = new Comment(['id' => 1, 'user_id' => 2]);
        $author = $comment->author;

        $this->assertEquals(2, $author->id);
    }

    /**
     * tests deep associations with lazy loaded entities
     *
     * @return void
     */
    public function testDeepLazyLoad()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->entityClass('\JeremyHarris\LazyLoad\TestApp\Model\Entity\LazyLoadableEntity');
        $this->Comments->belongsTo('Users');

        $article = $this->Articles->get(1);

        $comments = $article->comments;

        $expected = [
            1 => 'nate',
            2 => 'garrett',
            3 => 'mariano',
            4 => 'mariano',
        ];
        foreach ($comments as $comment) {
            $this->assertEquals($expected[$comment->id], $comment->user->username);
        }
    }

    /**
     * tests lazy loading
     *
     * @return void
     */
    public function testLazyLoad()
    {
        $article = $this->Articles->get(1);
        $tags = $article->tags;

        $this->assertEquals(2, count($tags));
    }

    /**
     * tests lazy loading
     *
     * @return void
     */
    public function testLazyLoadNonExistent()
    {
        $this->Articles->Authors->eventManager()
            ->on('Model.beforeFind', function (Event $event, Query $query) {
                $query->formatResults(function ($resultSet) {
                    return $resultSet;
                });
            });
        $article = $this->Articles->get(4);
        $author = $article->author;

        $this->assertNull($author);
    }

    /**
     * tests has()
     *
     * @return void
     */
    public function testHas()
    {
        $article = $this->Articles->get(1);

        $serialized = $article->toArray();
        $this->assertArrayNotHasKey('author', $serialized);

        $this->assertTrue($article->has('author'));
    }

    /**
     * tests that if we contain an association, the lazy loader doesn't overwrite
     * it
     *
     * @return void
     */
    public function testDontInterfereWithContain()
    {
        $this->Articles = $this->getMockForModel('Articles', ['_lazyLoad'], ['table' => 'articles']);
        $this->Articles->belongsTo('Authors');

        $this->Articles
            ->expects($this->never())
            ->method('_lazyLoad');

        $article = $this->Articles->find()->contain('Authors')->first();

        $this->assertEquals('mariano', $article->author->name);
    }
}
