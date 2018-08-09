<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use LittleGiant\BatchWrite\Extensions\WriteCallbackExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Dog
 *
 * @package BatchWrite\Tests
 * @property string $Type
 * @property string $Color
 * @property int $OwnerID
 * @method Human|null Owner()
 * @mixin WriteCallbackExtension
 */
class Dog extends Animal implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Type'  => DBVarchar::class,
        'Color' => DBVarchar::class,
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Owner' => Human::class,
    ];
}
