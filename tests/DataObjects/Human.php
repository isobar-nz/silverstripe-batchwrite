<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Human
 * @package BatchWrite\Tests
 */
class Human extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = array(
        'Name' => 'Varchar',
    );

    /**
     * @var array
     */
    private static $many_many = array(
        'Children' => 'BatchWrite\Tests\Child',
    );
}
