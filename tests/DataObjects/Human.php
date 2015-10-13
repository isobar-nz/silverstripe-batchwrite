<?php

namespace BatchWrite\Tests;

class Human extends \DataObject implements \TestOnly
{
    public static $db = array(
        'Name' => 'Varchar',
    );

    public static $many_many = array(
        'Children' => 'BatchWrite\Tests\Child',
    );
}
