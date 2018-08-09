<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\Batch;
use LittleGiant\BatchWrite\Tests\DataObjects\Animal;
use LittleGiant\BatchWrite\Tests\DataObjects\Batman;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\Dev\SapphireTest;

/**
 * Class BatchWriteManyManyTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchWriteManyManyTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected $extraDataObjects = array(
        Animal::class,
        Batman::class,
        Cat::class,
        Child::class,
        Child::class,
        Dog::class,
        DogPage::class,
        Human::class,
    );

    /**
     * BatchWriteManyManyTest constructor.
     */
    public function __construct()
    {
        $this->setUpOnce();
    }

    /**
     *
     */
    public function testWriteManyMany_CreateParentAndChildren_WritesManyMany()
    {
        $parent = new Batman();
        $parent->Name = 'Bruce Wayne';
        $parent->Car = 'Bat mobile';

        $children = array();
        for ($i = 0; $i < 5; $i++) {
            $child = new Child();
            $child->Name = 'Soldier #' . $i;
            $children[] = $child;
        }

        $batch = new Batch();

        $batch->write(array($parent));
        $batch->write($children);

        $sets = array();
        foreach ($children as $child) {
            $sets[] = array($parent, 'Children', $child);
        }
        $batch->writeManyMany($sets);

        $parent = Human::get()->first();
        $this->assertEquals(5, $parent->Children()->Count());
    }
//
//    public static function tearDownAfterClass()
//    {
//        parent::tearDownAfterClass();
//        \SapphireTest::delete_all_temp_dbs();
//    }
}
