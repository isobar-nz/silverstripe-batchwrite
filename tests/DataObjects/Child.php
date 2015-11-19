<?php

namespace BatchWrite\Tests;

/**
 * Class Child
 * @package BatchWrite\Tests
 */
class Child extends \DataObject implements \TestOnly
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
