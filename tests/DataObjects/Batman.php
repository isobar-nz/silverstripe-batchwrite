<?php

namespace BatchWrite\Tests;

class Batman extends Human implements \TestOnly
{
    public static $db = array(
        'Car' => 'Varchar',
    );
}
