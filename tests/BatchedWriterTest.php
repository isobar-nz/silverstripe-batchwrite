<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\BatchedWriter;
use LittleGiant\BatchWrite\Helpers\OnAfterExists;
use LittleGiant\BatchWrite\Tests\DataObjects\Animal;
use LittleGiant\BatchWrite\Tests\DataObjects\Batman;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * Class BatchedWriterTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchedWriterTest extends SapphireTest
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
     *
     */
    public function testWrite_WriteObjects_ObjectsWritten()
    {
        $batchSizes = [10, 30, 100, 300];

        foreach ($batchSizes as $size) {

            $owners = [];
            $dogs = [];
            $cats = [];

            $writer = new BatchedWriter($size);

            for ($i = 0; $i < 100; $i++) {
                $owner = new Human();
                $owner->Name = 'Human ' . $i;

                $dog = new Dog();
                $dog->Name = 'Dog ' . $i;

                $cat = new Cat();
                $cat->Name = 'Cat ' . $i;

                $owner->onAfterExistsCallback(function ($owner) use ($dog, $writer) {
                    $dog->OwnerID = $owner->ID;
                    $writer->write($dog);
                });

                $dog->onAfterExistsCallback(function ($dog) use ($cat, $writer) {
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

            $this->assertEquals(100, $owners->Count());
            $this->assertEquals(100, $dogs->Count());
            $this->assertEquals(100, $cats->Count());

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

            $this->assertEquals(0, Human::get()->Count());
            $this->assertEquals(0, Dog::get()->Count());
            $this->assertEquals(0, Cat::get()->Count());
        }
    }

    /**
     *
     */
    public function testWriteManyMany_SetChildrenForParent_RelationWritten()
    {
        $parent = new Human();
        $parent->Name = 'Bob';

        $children = [];
        for ($i = 0; $i < 5; $i++) {
            $child = new Child();
            $child->Name = 'Soldier #' . $i;
            $children[] = $child;
        }

        $writer = new BatchedWriter();

        $afterExists = new OnAfterExists(function () use ($writer, $parent, $children) {
            $writer->writeManyMany($parent, 'Children', $children);
        });

        $afterExists->addCondition($parent);
        $afterExists->addCondition($children);

        $writer->write([$parent]);
        $writer->write($children);
        $writer->finish();

        /** @var Human $parent */
        $parent = Human::get()->first();
        $this->assertEquals(5, $parent->Children()->Count());
    }

    /**
     *
     */
    public function testWriteToStages_ManyPages_WritesObjectsToStage()
    {
        $sizes = [10, 30, 100, 300];

        foreach ($sizes as $size) {
            $writer = new BatchedWriter($size);

            $pages = [];
            for ($i = 0; $i < 100; $i++) {
                $page = new DogPage();
                $page->Title = 'Wonder Pup  ' . $i;
                $pages[] = $page;
            }

            $writer->writeToStage($pages, 'Stage');
            $writer->finish();

            $currentStage = Versioned::current_stage();

            Versioned::reading_stage('Stage');
            $this->assertEquals(100, DogPage::get()->Count());

            Versioned::reading_stage('Live');
            $this->assertEquals(0, DogPage::get()->Count());

            $writer->writeToStage($pages, 'Live');
            $writer->finish();

            Versioned::reading_stage('Live');
            $this->assertEquals(100, DogPage::get()->Count());

            Versioned::reading_stage($currentStage);

            $writer->deleteFromStage($pages, 'Stage', 'Live');
            $writer->finish();

            $this->assertEquals(0, DogPage::get()->Count());
        }
    }
}
