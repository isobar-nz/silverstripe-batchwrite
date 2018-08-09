<?php

/**
 * Class QuickDataObject
 */
class QuickDataObject
{
    /**
     * @var array
     */
    private static $instances = array();

    /**
     * @param $className
     * @return mixed
     */
    public static function create($className)
    {
        static $extensionInstanceProperty = null;

        if (!$extensionInstanceProperty) {
            $extensionInstanceProperty = new ReflectionProperty('Object', 'extension_instances');
            $extensionInstanceProperty->setAccessible(true);
        }

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }

        $object = clone self::$instances[$className];

        $originalExtensions = $extensionInstanceProperty->getValue($object);
        $extensions = array();
        foreach ($originalExtensions as $key => $extension) {
            $extensions[$key] = clone $extension;
        }

        $extensionInstanceProperty->setValue($object, $extensions);

        return $object;
    }
}
