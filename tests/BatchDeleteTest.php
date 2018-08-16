<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Batch;
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

        $this->assertCount(100, Dog::get());
        $this->assertCount(100, Human::get());

        $batch = Batch::create();
        $batch->delete($objects);

        $this->assertCount(0, Dog::get());
        $this->assertCount(0, Human::get());
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

        $this->assertCount(100, Dog::get());

        $batch = Batch::create();
        $batch->deleteIDs(Dog::class, $ids);

        $this->assertCount(0, Dog::get());
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
            $this->assertCount(100, DogPage::get());
            $batch->deleteFromStage($pages, Versioned::LIVE);
            $this->assertCount(0, DogPage::get());
        });

        Versioned::withVersionedMode(function () use ($batch, $pages) {
            Versioned::set_stage(Versioned::DRAFT);
            $this->assertCount(100, DogPage::get());
            $batch->deleteFromStage($pages, Versioned::DRAFT);
            $this->assertCount(0, DogPage::get());
        });
    }
}
