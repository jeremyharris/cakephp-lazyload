<?php

namespace JeremyHarris\LazyLoad\ORM;

use Cake\Datasource\RepositoryInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Entity;
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
     * Array of properties that have been unset
     *
     * @var array
     */
    protected $_unsetProperties = [];

    /**
     * Overrides get() to check for associated data to lazy load, if that
     * property doesn't already exist
     *
     * @param string $property Property
     * @return mixed
     */
    public function &get($property): mixed
    {
        $get = &$this->_parentGet($property);

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
        return Entity::get($property);
    }

    /**
     * Overrides has method to account for a lazy loaded property
     *
     * @param string|array $property Property
     * @return bool
     */
    public function has($property): bool
    {
        foreach ((array)$property as $prop) {
            $has = $this->_parentHas($prop);

            if ($has === false) {
                $has = $this->_lazyLoad($prop);
                if ($has === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Passthru for testing
     *
     * @param string $property Property
     * @return mixed
     */
    protected function _parentHas($property)
    {
        return Entity::has($property);
    }

    /**
     * Unsets a property, marking it as not to be lazily loaded in the future
     *
     * @param array|string $property Property
     * @return $this
     */
    public function unset($property)
    {
        $property = (array)$property;
        foreach ($property as $prop) {
            $this->_unsetProperties[] = $prop;
        }

        return Entity::unset($property);
    }

    /**
     * Lazy loads association data onto the entity
     *
     * @param string $property Property
     * @return mixed
     */
    protected function _lazyLoad($property)
    {
        // check if the property has been unset at some point
        if (array_search($property, $this->_unsetProperties) !== false) {
            return null;
        }

        // check if the property was set as null to begin with
        if (array_key_exists($property, $this->_fields)) {
            return $this->_fields[$property];
        }

        $repository = $this->_repository($property);
        if (!($repository instanceof RepositoryInterface)) {
            return null;
        }

        $association = $repository
            ->associations()
            ->getByProperty($property);

        // is belongsTo and missing FK on this table? loadInto tries to load belongsTo data regardless
        $isMissingBelongsToFK = $association instanceof BelongsTo && !isset($this->_fields[$association->getForeignKey()]);

        if ($association === null || $isMissingBelongsToFK) {
            return null;
        }

        $repository->loadInto($this, [$association->getName()]);

        // check if the association didn't exist and therefore didn't load
        if (!isset($this->_fields[$property])) {
            return null;
        }

        return $this->_fields[$property];
    }

    /**
     * Gets the repository for this entity
     *
     * @return Table
     */
    protected function _repository()
    {
        $source = $this->getSource();
        if (empty($source)) {
            list(, $class) = \namespaceSplit(get_class($this));
            $source = Inflector::pluralize($class);
        }

        return TableRegistry::getTableLocator()->get($source);
    }
}
