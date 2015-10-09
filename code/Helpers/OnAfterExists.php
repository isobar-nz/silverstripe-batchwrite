<?php

class OnAfterExists
{
    private $objects;

    private $callback;

    public function __construct(callable $callback)
    {
        $this->objects = new ArrayList();
        $this->callback = $callback;
    }

    public function addCondition($objects, callable $callback = null)
    {
        if ($objects instanceof DataObject) {
            $objects = array($objects);
        }

        foreach ($objects as $object) {
            $this->objects->add($object);

            $everyObject = $this->objects;
            $existsCallback = $this->callback;
            $object->onAfterExistsCallback(function ($object) use ($callback, $everyObject, $existsCallback) {
                if ($callback) {
                    $callback($object);
                }

                $exists = true;
                foreach ($everyObject as $object) {
                    if (!$object->exists()) {
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
