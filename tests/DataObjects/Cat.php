<?php

namespace BatchWrite\Tests;

/**
 * Class Cat
 * @package BatchWrite\Tests
 */
class Cat extends Animal implements \TestOnly
{
    /**
     * @var bool
     */
    private $onBeforeWriteCalled = false;

    /**
     * @var bool
     */
    private $onAfterWriteCalled = false;

    /**
     * @var array
     */
    public static $db = array(
        'Type' => 'Varchar',
        'HasClaws' => 'Boolean',
    );

    /**
     * @var array
     */
    public static $has_one = array(
        'Enemy' => 'BatchWrite\Tests\Dog',
    );

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->onBeforeWriteCalled = true;
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->onAfterWriteCalled = true;
    }

    /**
     * @return bool
     */
    public function getOnBeforeWriteCalled()
    {
        return $this->onBeforeWriteCalled;
    }

    /**
     * @return bool
     */
    public function getOnAfterWriteCalled()
    {
        return $this->onAfterWriteCalled;
    }
}
