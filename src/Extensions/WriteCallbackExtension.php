<?php

/**
 * Class WriteCallbackExtension
 */
class WriteCallbackExtension extends DataExtension
{
    /**
     * @var array
     */
    private $beforeWriteCallbacks = array();

    /**
     * @var array
     */
    private $afterWriteCallbacks = array();

    /**
     *
     */
    public function onBeforeWrite()
    {
        foreach ($this->beforeWriteCallbacks as $callback) {
            $callback($this->owner);
        }
        $this->beforeWriteCallbacks = array();
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        foreach ($this->afterWriteCallbacks as $callback) {
            $callback($this->owner);
        }
        $this->afterWriteCallbacks = array();
    }

    /**
     * @param callable $callback
     */
    public function onBeforeWriteCallback(callable $callback)
    {
        $this->beforeWriteCallbacks[] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function onAfterWriteCallback(callable $callback)
    {
        $this->afterWriteCallbacks[] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function onAfterExistsCallback(callable $callback)
    {
        // if object exists already then call immediately
        if ($this->owner->getField('ID')) {
            $callback($this->owner);
        } else {
            // otherwise wait until it's written
            $this->onAfterWriteCallback($callback);
        }
    }
}

