<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Batman
 * @package BatchWrite\Tests
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
