<?php

namespace BatchWrite\Tests;

/**
 * Class Human
 * @package BatchWrite\Tests
 */
class Human extends \DataObject implements \TestOnly
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
    public static $many_many = array(
        'Children' => 'BatchWrite\Tests\Child',
    );
}
