<?php
namespace JeremyHarris\LazyLoad\Test\TestCase\ORM;

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use JeremyHarris\LazyLoad\TestApp\Model\Entity\Comment;
use JeremyHarris\LazyLoad\TestApp\Model\Entity\LazyLoadableEntity;
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
        $this->Articles->entityClass(LazyLoadableEntity::class);
        $this->Articles->belongsTo('Authors');
        $this->Articles->hasMany('Comments');
        $this->Articles->belongsToMany('Tags', [
            'joinTable' => 'articles_tags',
        ]);
    }

    /**
     * tests formatting results on a lazy loaded non-existent record
     *
     * @return void
     */
    public function testFormatResultsNonExistentRecord()
    {
        $this->Articles->Authors->eventManager()
            ->on('Model.beforeFind', function ($event, $query) {
                $query->formatResults(function ($resultSet) {
                    return $resultSet;
                });
            });
        $article = $this->Articles->get(4);
        $author = $article->author;
        $this->assertNull($author);
    }

    /**
     * tests nullable associations
     *
     * @return void
     */
    public function testNullableAssociation()
    {
        $article = $this->Articles->get(4);
        $this->assertNull($article->author);
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
     * tests that unsetting a property doesn't reload it
     *
     * @return void
     */
    public function testUnsetProperty()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->getMockBuilder(Comment::class)
            ->setConstructorArgs([['id' => 1, 'user_id' => 2]])
            ->setMethods(['_repository'])
            ->getMock();

        $comment
            ->expects($this->once())
            ->method('_repository')
            ->will($this->returnValue($this->Comments));

        $this->assertInstanceOf(EntityInterface::class, $comment->author);
        $comment->unsetProperty('author');
        $this->assertNull($comment->author);

        // test re-setting a previously un-set prop
        $comment->author = 'manual set';
        $this->assertSame('manual set', $comment->author);
    }

    /**
     * tests that lazy loading a previously unset eager loaded property does not
     * reload the property
     *
     * @return void
     */
    public function testUnsetEagerLoadedProperty()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->entityClass(LazyLoadableEntity::class);
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->Comments->find()
            ->contain(['Authors'])
            ->first();

        $this->assertInstanceOf(EntityInterface::class, $comment->author);
        $comment->unsetProperty('author');
        $this->assertNull($comment->author);
    }

    /**
     * tests that we only has() lazy loads the first time and uses the natural get() after
     *
     * @return void
     */
    public function testHasLazyLoadsOnce()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->getMockBuilder(Comment::class)
            ->setConstructorArgs([['id' => 1, 'user_id' => 2]])
            ->setMethods(['_repository'])
            ->getMock();

        $comment
            ->expects($this->once())
            ->method('_repository')
            ->will($this->returnValue($this->Comments));

        $this->assertTrue($comment->has('author'));

        // ensure it is grabbed from _properties and not lazy loaded again (which calls repository())
        $this->assertTrue($comment->has('author'));
    }

    /**
     * tests that we only get() lazy loads the first time and returns from _properties after
     *
     * @return void
     */
    public function testGetLazyLoadsOnce()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->belongsTo('Authors', [
            'foreignKey' => 'user_id'
        ]);

        $comment = $this->getMockBuilder(Comment::class)
            ->setConstructorArgs([['id' => 1, 'user_id' => 2]])
            ->setMethods(['_repository'])
            ->getMock();

        $comment
            ->expects($this->once())
            ->method('_repository')
            ->will($this->returnValue($this->Comments));

        $author = $comment->author;

        $this->assertEquals(2, $author->id);

        // ensure it is grabbed from _properties and not lazy loaded again (which calls repository())
        $comment->author;
    }

    /**
     * tests that lazyload doesn't interfere with existing accessor methods
     *
     * @return void
     */
    public function testGetAccessor()
    {
        $this->Comments = TableRegistry::get('Comments');
        $this->Comments->entityClass(Comment::class);
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
     * tests entity's get()
     *
     * @return void
     */
    public function testEntityMethodGet()
    {
        $article = $this->Articles->get(1);
        $comments = $article->comments;
        $this->assertInternalType('array', $comments);
        $this->assertCount(4, $comments);
        $this->assertInstanceOf(\Cake\Datasource\EntityInterface::class, $comments[0]);
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
        $this->Comments->entityClass(LazyLoadableEntity::class);
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
     * tests has() with a arrays
     *
     * @return void
     */
    public function testHasArray()
    {
        $article = $this->Articles->get(1);

        $this->assertTrue($article->has(['author', 'author_id', 'id']));
        $this->assertFalse($article->has(['author', 'author_id', 'id', 'missing']));
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
