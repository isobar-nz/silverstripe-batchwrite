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
        $dataObjectRecordProperty = new ReflectionProperty('DataObject', 'record');
        $dataObjectRecordProperty->setAccessible(true);
        $fields = $dataObjectRecordProperty->getValue($this->owner);
        if (!empty($fields['ID'])) {
            $callback($this->owner);
        } else {
            $this->onAfterWriteCallback($callback);
        }
    }
}

