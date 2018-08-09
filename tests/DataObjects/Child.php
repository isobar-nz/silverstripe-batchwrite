<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Child
 * @package BatchWrite\Tests
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
