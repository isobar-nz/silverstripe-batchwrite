<?php

namespace BatchWrite\Tests;

/**
 * Class DogPage
 * @package BatchWrite\Tests
 */
class DogPage extends \DataObject implements \TestOnly
{
    /**
     * @var array
     */
    public static $db = array(
        'Title' => 'Varchar',
        'Author' => 'Varchar',
    );

    private static $extensions = array(
        "Versioned('Stage', 'Live')"
    );
}
