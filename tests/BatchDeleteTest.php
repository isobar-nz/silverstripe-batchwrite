<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\Batch;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class BatchDeleteTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchDeleteTest extends BaseTest
{
    /**
     * @throws ValidationException
     * @throws null
     */
    public function testBranchDelete_DeleteManyObjects_ObjectsDeleted()
    {
        $objects = [];
        for ($i = 0; $i < 100; $i++) {
            $human = Human::create();
            $human->Name = $this->faker->name;
            $human->write();

            $dog = Dog::create();
            $dog->Name = $this->faker->firstName;
            $dog->Color = $this->faker->colorName;
            $dog->OwnerID = $human->ID;
            $dog->write();

            $objects[] = $human;
            $objects[] = $dog;
        }

        $batch = Batch::create();
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
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $dog = Dog::create();
            $dog->Name = $this->faker->firstName;
            $dog->Color = $this->faker->colorName;
            $dog->write();
            $ids[] = $dog->ID;
        }

        $batch = Batch::create();
        $batch->deleteIDs(Dog::class, $ids);

        $this->assertEquals(0, Dog::get()->Count());
    }

    /**
     *
     */
    public function testBatchDelete_VersionedObject_ObjectsDeleted()
    {
        $pages = [];
        for ($i = 0; $i < 100; $i++) {
            $page = DogPage::create();
            $page->Title = $this->faker->firstName;
            $page->writeToStage(Versioned::DRAFT);
            $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
            $pages[] = $page;
        }

        $batch = Batch::create();

        Versioned::withVersionedMode(function () use ($batch, $pages) {
            Versioned::set_stage(Versioned::LIVE);
            $this->assertEquals(100, DogPage::get()->Count());
            $batch->deleteFromStage($pages, Versioned::LIVE);
            $this->assertEquals(0, DogPage::get()->Count());
        });

        Versioned::withVersionedMode(function () use ($batch, $pages) {
            Versioned::set_stage(Versioned::DRAFT);
            $this->assertEquals(100, DogPage::get()->Count());
            $batch->deleteFromStage($pages, Versioned::DRAFT);
            $this->assertEquals(0, DogPage::get()->Count());
        });
    }
}
