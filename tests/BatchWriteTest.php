<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\Batch;
use LittleGiant\BatchWrite\Tests\DataObjects\Animal;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class BatchWriteTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchWriteTest extends BaseTest
{
    /**
     *
     */
    public function testBatchWrite_WriteObject_ObjectExists()
    {
        $animal = Animal::create();
        $animal->Name = 'Bob';
        $animal->Country = 'Africa';

        $batch = Batch::create();
        $batch->write([$animal]);

        $this->assertTrue($animal->exists());
        $this->assertEquals(1, $animal->ID);
        $this->assertEquals(1, Animal::get()->count());
    }

    /**
     *
     */
    public function testBatchWrite_WriteLotsObjects_ObjectsExist()
    {
        $animals = [];

        for ($i = 0; $i < 100; $i++) {
            $animal = Animal::create();
            $animal->Name = "Bob {$i}";
            $animal->Country = "Africa {$i}";
            $animals[] = $animal;
        }

        $batch = Batch::create();
        $batch->write($animals);

        for ($i = 0; $i < 100; $i++) {
            $id = $animals[$i]->ID;
            $this->assertEquals($i + 1, $id);
        }

        $this->assertEquals(100, Animal::get()->count());
    }

    /**
     *
     */
    public function testBatchWrite_NestedObjects_ObjectsExist()
    {
        $dogs = [];

        for ($i = 0; $i < 100; $i++) {
            $dog = Dog::create();
            $dog->Name = "Bob {$i}";
            $dog->Country = "Africa {$i}";
            $dog->Type = "Woof Dog {$i}";
            $dog->Color = "Brown #{$i}";
            $dogs[] = $dog;
        }

        $batch = Batch::create();
        $batch->write($dogs);

        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($dogs[$i]->exists());
            $id = $dogs[$i]->ID;
            $this->assertEquals($i + 1, $id);
        }

        $this->assertEquals(100, Dog::get()->count());

        foreach (Animal::get() as $i => $dog) {
            $this->assertEquals("Bob {$i}", $dog->Name);
            $this->assertEquals("Brown #{$i}", $dog->Color);
        }
    }

    /**
     *
     */
    public function testBatchWrite_OnBeforeOnAfterCalled_ReturnsTrue()
    {
        $cat = Cat::create();
        $cat->Name = 'Garfield';
        $cat->Country = 'Canada';
        $cat->HasClaws = true;

        $batch = Batch::create();
        $batch->write([$cat]);

        $this->assertTrue($cat->exists());
        $this->assertEquals(1, $cat->ID);
        $this->assertTrue($cat->getOnBeforeWriteCalled());
        $this->assertTrue($cat->getOnAfterWriteCalled());
        $this->assertEquals(1, Cat::get()->count());
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testBatchWrite_ObjectExists_UpdatesObject()
    {
        $dog = Dog::create();
        $dog->Name = 'Harry';
        $dog->Type = 'Trotter';
        $dog->Color = 'Red';
        $dog->write();
        $dog->Name = 'Jimmy';
        $dog->Color = 'Brown';

        $batch = Batch::create();
        $batch->write([$dog]);

        /** @var Dog|null $dog */
        $dog = Dog::get()->byID($dog->ID);
        $this->assertEquals('Jimmy', $dog->Name);
        $this->assertEquals('Trotter', $dog->Type);
        $this->assertEquals('Brown', $dog->Color);
    }

    /**
     *
     */
    public function testBatchWrite_WriteObjectToStage_WritesStage()
    {
        $page = DogPage::create();
        $page->Title = 'I Love Dogs';
        $page->Author = 'Mr Scruffy';

        $batch = Batch::create();
        $batch->writeToStage([$page], Versioned::DRAFT);
        $this->assertEquals(1, $page->ID);

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);
            /** @var DogPage|null $page */
            $page = DogPage::get()->first();
            $this->assertNotNull($page);
            $this->assertEquals('I Love Dogs', $page->Title);
            $this->assertEquals('Mr Scruffy', $page->Author);
        });

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::LIVE);
            $page = DogPage::get()->first();
            $this->assertNull($page);
        });
    }

    /**
     *
     */
    public function testBatchWrite_WriteObjectToLive_WritesLive()
    {
        $page = DogPage::create();
        $page->Title = 'I Hate Bones';
        $page->Author = 'Mrs Tu tu';

        $batch = Batch::create();
        $batch->writeToStage([$page], 'Live');
        $this->assertEquals(1, $page->ID);

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);
            $page = DogPage::get()->first();
            $this->assertNull($page);
        });

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::LIVE);
            /** @var DogPage|null $page */
            $page = DogPage::get()->first();
            $this->assertNotNull($page);
            $this->assertEquals('I Hate Bones', $page->Title);
            $this->assertEquals('Mrs Tu tu', $page->Author);
        });
    }

    /**
     *
     */
    public function testBatchWrite_WriteObjectToStageAndLive_WritesStageAndLive()
    {
        $page = DogPage::create();
        $page->Title = 'WOOF';
        $page->Author = 'Woof Woof';

        $batch = Batch::create();
        $batch->writeToStage([$page], Versioned::DRAFT, Versioned::LIVE);
        $this->assertEquals(1, $page->ID);

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);
            /** @var DogPage|null $page */
            $page = DogPage::get()->first();
            $this->assertNotNull($page);
            $this->assertEquals('WOOF', $page->Title);
            $this->assertEquals('Woof Woof', $page->Author);
        });

        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::LIVE);
            /** @var DogPage|null $page */
            $page = DogPage::get()->first();
            $this->assertNotNull($page);
            $this->assertEquals('WOOF', $page->Title);
            $this->assertEquals('Woof Woof', $page->Author);
        });
    }

    /**
     *
     */
    public function testBatchWrite_DifferentClasses_WritesObjects()
    {
        $dog = Dog::create();
        $dog->Name = 'Snuffins';
        $dog->Color = 'Red';

        $cat = Cat::create();
        $cat->Name = 'Puff daddy';
        $cat->HasClaws = true;

        $batch = Batch::create();
        $batch->write([$dog, $cat]);

        $this->assertTrue($dog->exists());
        $this->assertTrue($cat->exists());

        /** @var Dog|null $dog */
        $dog = Dog::get()->first();
        $this->assertEquals('Snuffins', $dog->Name);
        $this->assertEquals('Red', $dog->Color);

        /** @var Cat|null $cat */
        $cat = Cat::get()->first();
        $this->assertEquals('Puff daddy', $cat->Name);
        $this->assertEquals(1, $cat->HasClaws);
    }
}
