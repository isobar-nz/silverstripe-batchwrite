<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\BatchedWriter;
use LittleGiant\BatchWrite\Helpers\OnAfterExists;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\Versioned\Versioned;

/**
 * Class BatchedWriterTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchedWriterTest extends BaseTest
{
    /**
     *
     */
    public function testWrite_WriteObjects_ObjectsWritten()
    {
        $batchSizes = [10, 30, 100, 300];

        foreach ($batchSizes as $size) {
            $owners = [];
            $dogs = [];
            $cats = [];

            $writer = BatchedWriter::create($size);

            for ($i = 0; $i < 100; $i++) {
                $owner = Human::create();
                $owner->Name = $this->faker->name;

                $dog = Dog::create();
                $dog->Name = $this->faker->firstName;

                $cat = Cat::create();
                $cat->Name = $this->faker->firstName;

                $owner->onAfterExistsCallback(function (Human $owner) use ($dog, $writer) {
                    $dog->OwnerID = $owner->ID;
                    $writer->write($dog);
                });

                $dog->onAfterExistsCallback(function (Dog $dog) use ($cat, $writer) {
                    $cat->EnemyID = $dog->ID;
                    $writer->write($cat);
                });

                $owners[] = $owner;
                $dogs[] = $dog;
                $cats[] = $cat;

            }

            // writes dogs first time
            $writer->write($dogs);

            // dogs written again from owner callback
            $writer->write($owners);

            $writer->finish();

            $owners = Human::get();
            $dogs = Dog::get();
            $cats = Cat::get();

            $this->assertCount(100, $owners);
            $this->assertCount(100, $dogs);
            $this->assertCount(100, $cats);

            for ($i = 0; $i < 100; $i++) {
                $owner = $owners[$i];
                $dog = $dogs[$i];
                $cat = $cats[$i];

                $this->assertEquals($owner->ID, $dog->OwnerID);
                $this->assertEquals($dog->ID, $cat->EnemyID);
            }

            $writer->delete($owners);
            $writer->delete($dogs);
            $writer->delete($cats);
            $writer->finish();

            $this->assertCount(0, Human::get());
            $this->assertCount(0, Dog::get());
            $this->assertCount(0, Cat::get());
        }
    }

    /**
     *
     */
    public function testWriteManyMany_SetChildrenForParent_RelationWritten()
    {
        $parent = Human::create();
        $parent->Name = $this->faker->name;

        $children = [];
        for ($i = 0; $i < 5; $i++) {
            $child = Child::create();
            $child->Name = $this->faker->name;
            $children[] = $child;
        }

        $this->assertCount(0, Human::get());
        $this->assertCount(0, Child::get());

        $writer = BatchedWriter::create();

        $afterExists = OnAfterExists::create(function () use ($writer, $parent, $children) {
            $writer->writeManyMany($parent, 'Children', $children);
        });

        $afterExists->addCondition($parent);
        $afterExists->addCondition($children);

        $writer->write([$parent]);
        $writer->write($children);
        $writer->finish();

        /** @var Human $parent */
        $parent = Human::get()->first();
        $this->assertCount(5, $parent->Children());
    }

    /**
     *
     */
    public function testWriteToStages_ManyPages_WritesObjectsToStage()
    {
        $sizes = [10, 30, 100, 300];

        foreach ($sizes as $size) {
            $writer = BatchedWriter::create($size);

            $pages = [];
            for ($i = 0; $i < 100; $i++) {
                $page = DogPage::create();
                $page->Title = "Wonder Pup {$i}";
                $pages[] = $page;
            }

            $writer->writeToStage($pages, Versioned::DRAFT);
            $writer->finish();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::DRAFT);
                $this->assertCount(100, DogPage::get());
            });

            Versioned::withVersionedMode(function () use ($writer, $pages) {
                Versioned::set_stage(Versioned::LIVE);
                $this->assertCount(0, DogPage::get());

                $writer->writeToStage($pages, Versioned::LIVE);
                $writer->finish();

                $this->assertCount(100, DogPage::get());
            });

            $writer->deleteFromStage($pages, Versioned::DRAFT, Versioned::LIVE);
            $writer->finish();

            $this->assertCount(0, DogPage::get());
        }
    }
}
