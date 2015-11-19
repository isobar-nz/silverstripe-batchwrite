<?php

namespace BatchWrite\Tests;

/**
 * Class Animal
 * @package BatchWrite\Tests
 */
class Animal extends \DataObject implements \TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Name' => 'Varchar',
        'Country' => 'Varchar',
    );
}
