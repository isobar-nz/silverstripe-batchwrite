<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;

/**
 * Class Dog
 * @package BatchWrite\Tests
 */
class Dog extends Animal implements TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Type' => 'Varchar',
        'Color' => 'Varchar',
    );

    /**
     * @var array
     */
    public static $has_one = array(
        'Owner' => 'BatchWrite\Tests\Human',
    );
}
