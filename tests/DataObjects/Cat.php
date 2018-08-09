<?php

namespace LittleGiant\BatchWrite\Tests\DataObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class Cat
 * @package BatchWrite\Tests
 */
class Cat extends Animal implements TestOnly
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
    private static $db = [
        'Type'     => DBVarchar::class,
        'HasClaws' => DBBoolean::class,
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Enemy' => Dog::class,
    ];

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
