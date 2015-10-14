<?php

/**
 * Class OnAfterExists
 */
class OnAfterExists
{
    /**
     * @var ArrayObject
     */
    private $objects;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var ReflectionProperty
     */
    private $dataObjectRecordProperty;

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->objects = new ArrayObject();
        $this->callback = $callback;

        $this->dataObjectRecordProperty = new ReflectionProperty('DataObject', 'record');
        $this->dataObjectRecordProperty->setAccessible(true);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->objects);
    }

    /**
     * @param $objects
     * @param callable $callback
     */
    public function addCondition($objects, callable $callback = null)
    {
        if ($objects instanceof DataObject) {
            $objects = array($objects);
        }

        foreach ($objects as $object) {
            $this->objects[] = $object;

            $everyObject = $this->objects;
            $existsCallback = $this->callback;
            $dataObjectProperty = $this->dataObjectRecordProperty;

            $object->onAfterExistsCallback(function ($object) use ($callback, $everyObject, $existsCallback, $dataObjectProperty) {
                if ($callback) {
                    $callback($object);
                }

                $exists = true;
                foreach ($everyObject as $object) {
                    $record = $dataObjectProperty->getValue($object);
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
