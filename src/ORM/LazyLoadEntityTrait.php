<?php
namespace JeremyHarris\LazyLoad\ORM;

use Cake\Datasource\RepositoryInterface;
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
     * @param string $property Property
     * @return mixed
     */
    public function &__get($property)
    {
        $get = $this->_parentGet($property);

        if ($get === null) {
            $get = $this->_lazyLoad($property);
        }

        return $get;
    }

    /**
     * Passthru for testing
     *
     * @param string $property Property
     * @return mixed
     */
    protected function &_parentGet($property)
    {
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
        $has = $this->_parentHas($property);

        if ($has === false) {
            $has = $this->_lazyLoad($property);

            return !empty($has);
        }

        return $has;
    }

    /**
     * Passthru for testing
     *
     * @param string $property Property
     * @return mixed
     */
    protected function _parentHas($property)
    {
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
        if (!($repository instanceof RepositoryInterface)) {
            return null;
        }

        $association = $repository
            ->associations()
            ->getByProperty($property);

        if ($association === null) {
            return null;
        }

        $repository->loadInto($this, [$association->name()]);

        if (!isset($this->_properties[$property])) {
            return null;
        }

        return $this->_properties[$property];
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
            list(, $class) = namespaceSplit(get_class($this));
            $source = Inflector::pluralize($class);
        }

        return TableRegistry::get($source);
    }
}
