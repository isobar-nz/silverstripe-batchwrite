<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use LittleGiant\BatchWrite\Extensions\WriteCallbackExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\ManyManyList;

/**
 * Class Human
 *
 * @package BatchWrite\Tests
 * @property string $Name
 * @method ManyManyList|Child[] Children()
 * @mixin WriteCallbackExtension
 */
class Human extends DataObject implements TestOnly
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
    private static $many_many = [
        'Children' => Child::class,
    ];
}
