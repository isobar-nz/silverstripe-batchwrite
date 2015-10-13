<?php

namespace BatchWrite\Tests;

class Child extends \DataObject
{
    public static $db = array(
        'Name' => 'Varchar',
    );

    private static $belongs_many_many = array(
        'BelongsParent' => 'BatchWrite\Tests\Human',
    );
}
