<?php

namespace BatchWrite\Tests;

/**
 * Class Batman
 * @package BatchWrite\Tests
 */
class Batman extends Human implements \TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Car' => 'Varchar',
    );
}
