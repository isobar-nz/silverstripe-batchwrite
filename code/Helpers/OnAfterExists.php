<?php

class OnAfterExists
{
    private $objects;

    private $callback;

    private $dataObjectRecordProperty;

    public function __construct(callable $callback)
    {
        $this->objects = new ArrayObject();
        $this->callback = $callback;

        $this->dataObjectRecordProperty = new ReflectionProperty('DataObject', 'record');
        $this->dataObjectRecordProperty->setAccessible(true);
    }

    public function addCondition($objects, callable $callback = null)
    {
        if ($objects instanceof DataObject) {
            $objects = array($objects);
        }

        foreach ($objects as $object) {
            $this->objects[] = $object;

            $everyObject = $this->objects;
            $existsCallback = $this->callback;
            $object->onAfterExistsCallback(function ($object) use ($callback, $everyObject, $existsCallback) {
                if ($callback) {
                    $callback($object);
                }

                $exists = true;
                foreach ($everyObject as $object) {
                    $record = $this->dataObjectRecordProperty->getValue($object);
                    if (empty($record['ID'])) {
                        $exists = false;
                        break;
                    }
                }

                if ($exists) {
                    $existsCallback();
                }
            });
        }
    }
}
