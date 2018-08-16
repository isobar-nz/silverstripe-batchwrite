<?php

namespace LittleGiant\BatchWrite\Extensions;

use SilverStripe\ORM\DataExtension;

/**
 * Class WriteCallbackExtension
 * @package LittleGiant\BatchWrite\Extensions
 */
class WriteCallbackExtension extends DataExtension
{
    const ON_BEFORE_WRITE_FIELD = self::class . '_onBeforeWriteCallbacks';
    const ON_AFTER_WRITE_FIELD = self::class . '_onAfterWriteCallbacks';

    /**
     *
     */
    public function onBeforeWrite()
    {
        $callbackArray = $this->owner->getField(static::ON_BEFORE_WRITE_FIELD);
        if (!$callbackArray instanceof \ArrayObject) return;

        $runCallbacks = $callbackArray->exchangeArray([]);

        foreach ($runCallbacks as $callback) {
            $callback($this->owner);
        }
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        $callbackArray = $this->owner->getField(static::ON_AFTER_WRITE_FIELD);
        if (!$callbackArray instanceof \ArrayObject) return;

        $runCallbacks = $callbackArray->exchangeArray([]);

        foreach ($runCallbacks as $callback) {
            $callback($this->owner);
        }
    }

    /**
     * @param callable $callback
     */
    public function onBeforeWriteCallback(callable $callback)
    {
        if (!$this->owner->hasField(static::ON_BEFORE_WRITE_FIELD)) {
            $this->owner->setField(static::ON_BEFORE_WRITE_FIELD, new \ArrayObject());
        }

        /** @var \ArrayObject $callbackArray */
        $callbackArray = $this->owner->getField(static::ON_BEFORE_WRITE_FIELD);
        $callbackArray->append($callback);
    }

    /**
     * @param callable $callback
     */
    public function onAfterWriteCallback(callable $callback)
    {
        if (!$this->owner->hasField(static::ON_AFTER_WRITE_FIELD)) {
            $this->owner->setField(static::ON_AFTER_WRITE_FIELD, new \ArrayObject());
        }

        /** @var \ArrayObject $callbackArray */
        $callbackArray = $this->owner->getField(static::ON_AFTER_WRITE_FIELD);
        $callbackArray->append($callback);
    }

    /**
     * @param callable $callback
     */
    public function onAfterExistsCallback(callable $callback)
    {
        // if object exists already then call immediately
        if ($this->owner->isInDB()) {
            $callback($this->owner);
        } else {
            // otherwise wait until it's written
            $this->onAfterWriteCallback($callback);
        }
    }
}

