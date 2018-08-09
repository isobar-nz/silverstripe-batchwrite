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
    private static $db = array(
        'Name' => 'Varchar',
    );

    /**
     * @var array
     */
    private static $belongs_many_many = array(
        'BelongsParent' => 'BatchWrite\Tests\Human',
    );
}
