<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Tests\DataObjects\Animal;
use LittleGiant\BatchWrite\Tests\DataObjects\Batman;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/**
 * Class BaseTest
 * @package LittleGiant\BatchWrite\Tests
 */
abstract class BaseTest extends SapphireTest
{
    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        Animal::class,
        Batman::class,
        Cat::class,
        Child::class,
        Dog::class,
        DogPage::class,
        Human::class,
    ];

    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        parent::tearDown();

        foreach (static::$extra_dataobjects as $dataobject) {
            // TODO fix tests requiring fresh table every time
            $table = DataObject::getSchema()->baseDataTable($dataobject);
            DB::query("TRUNCATE {$table}");
        }
    }
}
