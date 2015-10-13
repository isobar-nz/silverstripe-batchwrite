<?php

namespace BatchWrite\Tests;

class DogPage extends \SiteTree implements \TestOnly
{
    public static $db = array(
        'Author' => 'Varchar',
    );
}
