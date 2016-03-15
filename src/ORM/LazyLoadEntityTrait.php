<?php
namespace JeremyHarris\LazyLoad\ORM;

use Cake\Core\App;
use Cake\ORM\Association;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * LazyLoadEntity trait
 *
 * Lazily loads associated data when it doesn't exist and is requested on the
 * entity
 */
trait LazyLoadEntityTrait
{

    /**
     * Overrides magic get to check for associated data to lazy load, if that
     * property doesn't already exist
     *
     * @param strig $property Property
     * @return mixed
     */
    public function &__get($property)
    {
        $entityHas = parent::has($property);

        if ($entityHas === false) {
            $this->_lazyLoad($property);
        }

        return parent::__get($property);
    }

    /**
     * Overrides has method to account for a lazy loaded property
     *
     * @param string $property Property
     * @return bool
     */
    public function has($property)
    {
        $entityHas = parent::has($property);

        if ($entityHas === false) {
            $this->_lazyLoad($property);
        }

        return parent::has($property);
    }

    /**
     * Lazy loads association data onto the entity
     *
     * @param string $property Property
     * @return void
     */
    protected function _lazyLoad($property)
    {
        $repository = $this->_repository($property);

        $association = $repository
            ->associations()
            ->getByProperty($property);

        if ($association === null) {
            return;
        }

        $repository->loadInto($this, [$association->name()]);
    }

    /**
     * Gets the repository for this entity
     *
     * @return Table
     */
    protected function _repository()
    {
        $source = $this->source();

        if ($source === null) {
            $fullClass = get_class($this);
            list($namespace, $class) = namespaceSplit($fullClass);

            if(substr($namespace, -12) === 'Model\\Entity') {
                $source = Inflector::pluralize(App::shortName($fullClass, 'Model/Entity', ''));
            }else{
                $source = Inflector::pluralize($class);
            }
        }

        return TableRegistry::get($source);
    }
}
