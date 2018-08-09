<?php

namespace LittleGiant\SilverStripe\BatchWrite\Helpers;

use ReflectionProperty;

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
        $extensionInstanceProperty = new ReflectionProperty($className, 'extension_instances');
        $extensionInstanceProperty->setAccessible(true);

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = singleton($className);
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
