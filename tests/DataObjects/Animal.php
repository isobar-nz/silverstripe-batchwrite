<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use LittleGiant\BatchWrite\Extensions\WriteCallbackExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Animal
 *
 * @package BatchWrite\Tests
 * @property string $Name
 * @property string $Country
 * @mixin WriteCallbackExtension
 */
class Animal extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Name'    => DBVarchar::class,
        'Country' => DBVarchar::class,
    ];
}
