<?php

namespace BatchWrite\Tests;

class Animal extends \DataObject
{
    public static $db = array(
        'Name' => 'Varchar',
        'Country' => 'Varchar',
    );
}
