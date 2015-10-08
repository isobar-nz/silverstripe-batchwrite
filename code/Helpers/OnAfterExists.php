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

    public function addCondition($object, callable $callback = null)
    {
        $this->objects->add($object);

        $objects = $this->objects;
        $existsCallback = $this->callback;
        $object->onAfterExistsCallback(function ($object) use ($callback, $objects, $existsCallback) {
            if ($callback) {
                $callback($object);
            }

            $exists = true;
            foreach ($objects as $object) {
                $exists = $exists && $object->exists();
            }

            if ($exists) {
                $existsCallback();
            }
        });
    }
}
