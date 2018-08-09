<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Child
 * @package BatchWrite\Tests
 */
class Child extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Name' => 'Varchar',
    );

    /**
     * @var array
     */
    public static $belongs_many_many = array(
        'BelongsParent' => 'BatchWrite\Tests\Human',
    );
}
