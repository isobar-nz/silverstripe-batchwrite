<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Dog
 * @package BatchWrite\Tests
 */
class Dog extends Animal implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Type'  => DBVarchar::class,
        'Color' => DBVarchar::class,
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Owner' => Human::class,
    ];
}
