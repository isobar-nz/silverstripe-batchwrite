<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Human
 * @package BatchWrite\Tests
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
