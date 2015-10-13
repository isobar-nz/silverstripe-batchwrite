<?php

namespace BatchWrite\Tests;

class Animal extends \DataObject implements \TestOnly
{
    public static $db = array(
        'Name' => 'Varchar',
        'Country' => 'Varchar',
    );
}
