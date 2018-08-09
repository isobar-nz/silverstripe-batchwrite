<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Versioned\Versioned;

/**
 * Class DogPage
 * @package BatchWrite\Tests
 */
class DogPage extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Title'  => DBVarchar::class,
        'Author' => DBVarchar::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
