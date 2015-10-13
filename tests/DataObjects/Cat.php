<?php

namespace BatchWrite\Tests;

class Cat extends Animal implements \TestOnly
{
    private $onBeforeWriteCalled = false;

    private $onAfterWriteCalled = false;

    public static $db = array(
        'Type' => 'Varchar',
        'HasClaws' => 'Boolean',
    );

    public static $has_one = array(
        'Enemy' => 'BatchWrite\Tests\Dog',
    );

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->onBeforeWriteCalled = true;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->onAfterWriteCalled = true;
    }

    public function getOnBeforeWriteCalled()
    {
        return $this->onBeforeWriteCalled;
    }

    public function getOnAfterWriteCalled()
    {
        return $this->onAfterWriteCalled;
    }
}
