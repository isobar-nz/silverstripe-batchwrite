<?php

namespace BatchWrite\Tests;

class Dog extends Animal
{
    public static $db = array(
        'Type' => 'Varchar',
        'Color' => 'Varchar',
    );

    public static $has_one = array(
        'Owner' => 'BatchWrite\Tests\Human',
    );
}
