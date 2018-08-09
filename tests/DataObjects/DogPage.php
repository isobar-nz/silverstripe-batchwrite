<?php

namespace BatchWrite\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class DogPage
 * @package BatchWrite\Tests
 */
class DogPage extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = array(
        'Title' => 'Varchar',
        'Author' => 'Varchar',
    );

    private static $extensions = array(
        "Versioned('Stage', 'Live')"
    );
}
