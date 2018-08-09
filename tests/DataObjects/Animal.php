<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Animal
 * @package BatchWrite\Tests
 */
class Animal extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Name' => 'Varchar',
        'Country' => 'Varchar',
    );
}
