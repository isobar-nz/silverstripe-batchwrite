<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use LittleGiant\BatchWrite\Extensions\WriteCallbackExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\ManyManyList;

/**
 * Class Child
 *
 * @package BatchWrite\Tests
 * @property string $Name
 * @method ManyManyList|Human[] BelongsParent()
 * @mixin WriteCallbackExtension
 */
class Child extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Name' => DBVarchar::class,
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'BelongsParent' => Human::class,
    ];
}
