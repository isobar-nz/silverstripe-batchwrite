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
    private static $db = array(
        'Name' => 'Varchar',
        'Country' => 'Varchar',
    );
}
