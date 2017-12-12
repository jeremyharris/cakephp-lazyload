<?php
namespace JeremyHarris\LazyLoad\TestApp\Model\Entity;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

class User extends LazyLoadableEntity
{
    // to test including the trait twice
    use LazyLoadEntityTrait;

    protected function _getAccessor()
    {
        return 'accessor';
    }
}
