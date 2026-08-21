<?php
declare(strict_types=1);

namespace JeremyHarris\LazyLoad\ORM;

use Cake\Datasource\Exception\MissingPropertyException;
use Cake\Datasource\RepositoryInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Error;

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
     * @param string $field
     * @return mixed
     */
    public function &__get(string $field): mixed
    {
        return $this->getRequiredOrFail($field, $this->requireFieldPresence ?? false);
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function &get(string $field): mixed
    {
        return $this->getRequiredOrFail($field, true);
    }

    /**
     * Overrides getRequiredOrFail() to check for associated data to lazy load, if that
     * property doesn't already exist.
     *
     * @param string $field Property
     * @return mixed
     */
    public function &getRequiredOrFail(string $field, bool $requireFieldPresence = true): mixed
    {
        $e = null;
        $get = null;

        try {
            // CakePHP >= 5.3
            $get = &Entity::getRequiredOrFail($field, $requireFieldPresence);
        } catch (Error $err) {
            // CakePHP < 5.3
            $get = &Entity::get($field);
        } catch (MissingPropertyException $e) {}

        if ($get === null) {
            $get = $this->_lazyLoad($field);
        }

        if ($get === null && $requireFieldPresence && $e && !$this->isNew()) {
            throw $e;
        }

        return $get;
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
            $has = Entity::has($property);

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

        $repository = $this->_repository();
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
