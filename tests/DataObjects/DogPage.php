<?php

namespace BatchWrite\Tests;

/**
 * Class DogPage
 * @package BatchWrite\Tests
 */
class DogPage extends \SiteTree implements \TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Author' => 'Varchar',
    );
}
