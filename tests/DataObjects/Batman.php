<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use LittleGiant\BatchWrite\Extensions\WriteCallbackExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Batman
 *
 * @package BatchWrite\Tests
 * @property string $Car
 * @mixin WriteCallbackExtension
 */
class Batman extends Human implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Car' => DBVarchar::class,
    ];
}
