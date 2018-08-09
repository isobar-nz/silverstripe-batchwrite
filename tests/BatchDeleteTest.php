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
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class BatchDeleteTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchDeleteTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected $extraDataObjects = [
        Animal::class,
        Batman::class,
        Cat::class,
        Child::class,
        Child::class,
        Dog::class,
        DogPage::class,
        Human::class,
    ];

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testBranchDelete_DeleteManyObjects_ObjectsDeleted()
    {
        $objects = [];
        for ($i = 0; $i < 100; $i++) {
            $human = new Human();
            $human->Name = 'Proud Owner ' . $i;
            $human->write();

            $dog = new Dog();
            $dog->Name = 'Pup ' . $i;
            $dog->Color = 'Fifty Shade No. ' . $i;
            $dog->OwnerID = $human->ID;
            $dog->write();

            $objects[] = $human;
            $objects[] = $dog;
        }

        $batch = new Batch();
        $batch->delete($objects);

        $this->assertEquals(0, Dog::get()->Count());
        $this->assertEquals(0, Human::get()->Count());
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testBranchDeleteIDs_DeleteManyIDs_ObjectsDeleted()
    {
        $className = '';
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $dog = new Dog();
            $dog->Name = 'Pup ' . $i;
            $dog->Color = 'Fifty Shade No. ' . $i;
            $dog->write();
            $className = $dog->ClassName;
            $ids[] = $dog->ID;
        }

        $batch = new Batch();
        $batch->deleteIDs($className, $ids);

        $this->assertEquals(0, Dog::get()->Count());
    }

    /**
     *
     */
    public function testBatchDelete_VersionedObject_ObjectsDeleted()
    {
        $pages = [];
        for ($i = 0; $i < 100; $i++) {
            $page = new DogPage();
            $page->Title = 'Hero Dog ' . $i;
            $page->writeToStage('Stage');
            $page->publish('Stage', 'Live');
            $pages[] = $page;
        }

        $batch = new Batch();

        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Live');

        $this->assertEquals(100, DogPage::get()->Count());

        $batch->deleteFromStage($pages, 'Live');

        $this->assertEquals(0, DogPage::get()->Count());

        Versioned::reading_stage('Stage');

        $this->assertEquals(100, DogPage::get()->Count());

        $batch->deleteFromStage($pages, 'Stage');

        $this->assertEquals(0, DogPage::get()->Count());

        Versioned::reading_stage($currentStage);
    }
}
