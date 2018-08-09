<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;

/**
 * Class Batman
 * @package BatchWrite\Tests
 */
class Batman extends Human implements TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Car' => 'Varchar',
    );
}
