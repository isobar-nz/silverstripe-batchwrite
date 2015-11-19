<?php

namespace BatchWrite\Tests;

/**
 * Class BatchWriteTest
 * @package BatchWrite\Tests
 */
/**
 * Class BatchWriteTest
 * @package BatchWrite\Tests
 */
class BatchWriteTest extends \SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected $extraDataObjects = array(
        'BatchWrite\Tests\Animal',
        'BatchWrite\Tests\Batman',
        'BatchWrite\Tests\Cat',
        'BatchWrite\Tests\Child',
        'BatchWrite\Tests\Child',
        'BatchWrite\Tests\Dog',
        'BatchWrite\Tests\DogPage',
        'BatchWrite\Tests\Human',
    );

    /**
     * BatchWriteTest constructor.
     */
    public function __construct()
    {
        $this->setUpOnce();
    }

    /**
     *
     */
    public function testBatchWrite_WriteObject_ObjectExists()
    {
        $animal = new Animal();
        $animal->Name = 'Bob';
        $animal->Country = 'Africa';

        $batch = new \Batch();
        $batch->write(array($animal));

        $this->assertTrue($animal->exists());
        $this->assertEquals(1, $animal->ID);
        $this->assertEquals(1, Animal::get()->count());
    }

    /**
     *
     */
    public function testBatchWrite_WriteLotsObjects_ObjectsExist()
    {
        $animals = array();

        for ($i = 0; $i < 100; $i++) {
            $animal = new Animal();
            $animal->Name = 'Bob ' . $i;
            $animal->Country = 'Africa ' . $i;
            $animals[] = $animal;
        }

        $batch = new \Batch();
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
        $dogs = array();

        for ($i = 0; $i < 100; $i++) {
            $dog = new Dog();
            $dog->Name = 'Bob ' . $i;
            $dog->Country = 'Africa ' . $i;
            $dog->Type = 'Woof Dog ' . $i;
            $dog->Color = 'Brown #' . $i;
            $dogs[] = $dog;
        }

        $batch = new \Batch();
        $batch->write($dogs);

        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($dogs[$i]->exists());
            $id = $dogs[$i]->ID;
            $this->assertEquals($i + 1, $id);
        }

        $this->assertEquals(100, Dog::get()->count());

        foreach (Animal::get() as $i => $dog) {
            $this->assertEquals('Bob ' . $i, $dog->Name);
            $this->assertEquals('Brown #'. $i, $dog->Color);
        }
    }

    /**
     *
     */
    public function testBatchWrite_OnBeforeOnAfterCalled_ReturnsTrue()
    {
        $cat = new Cat();
        $cat->Name = 'Garfield';
        $cat->Country = 'Canada';
        $cat->HasClaws = true;

        $batch = new \Batch();
        $batch->write(array($cat));

        $this->assertTrue($cat->exists());
        $this->assertEquals(1, $cat->ID);
        $this->assertTrue($cat->getOnBeforeWriteCalled());
        $this->assertTrue($cat->getOnAfterWriteCalled());
        $this->assertEquals(1, Cat::get()->count());
    }

    /**
     * @throws \ValidationException
     * @throws null
     */
    public function testBatchWrite_ObjectExists_UpdatesObject()
    {
        $dog = new Dog();
        $dog->Name = 'Harry';
        $dog->Type = 'Trotter';
        $dog->Color = 'Red';
        $dog->write();
        $dog->Name = 'Jimmy';
        $dog->Color = 'Brown';

        $batch = new \Batch();
        $batch->write(array($dog));

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
        $page = new DogPage();
        $page->Title = 'I Love Dogs';
        $page->Author = 'Mr Scruffy';

        $batch = new \Batch();
        $batch->writeToStage(array($page), 'Stage');
        $this->assertEquals(1, $page->ID);

        $currentStage = \Versioned::current_stage();

        \Versioned::reading_stage('Stage');
        $page = DogPage::get()->first();
        $this->assertNotNull($page);
        $this->assertEquals('I Love Dogs', $page->Title);
        $this->assertEquals('Mr Scruffy', $page->Author);

        \Versioned::reading_stage('Live');
        $page = DogPage::get()->first();
        $this->assertNull($page);

        \Versioned::reading_stage($currentStage);
    }

    /**
     *
     */
    public function testBatchWrite_WriteObjectToLive_WritesLive()
    {
        $page = new DogPage();
        $page->Title = 'I Hate Bones';
        $page->Author = 'Mrs Tu tu';

        $batch = new \Batch();
        $batch->writeToStage(array($page), 'Live');
        $this->assertEquals(1, $page->ID);

        $currentStage = \Versioned::current_stage();

        \Versioned::reading_stage('Stage');
        $page = DogPage::get()->first();
        $this->assertNull($page);

        \Versioned::reading_stage('Live');
        $page = DogPage::get()->first();
        $this->assertNotNull($page);
        $this->assertEquals('I Hate Bones', $page->Title);
        $this->assertEquals('Mrs Tu tu', $page->Author);

        \Versioned::reading_stage($currentStage);
    }

    /**
     *
     */
    public function testBatchWrite_WriteObjectToStageAndLive_WritesStageAndLive()
    {
        $page = new DogPage();
        $page->Title = 'WOOF';
        $page->Author = 'Woof Woof';

        $batch = new \Batch();
        $batch->writeToStage(array($page), 'Stage', 'Live');
        $this->assertEquals(1, $page->ID);

        $currentStage = \Versioned::current_stage();

        \Versioned::reading_stage('Stage');
        $page = DogPage::get()->first();
        $this->assertNotNull($page);
        $this->assertEquals('WOOF', $page->Title);
        $this->assertEquals('Woof Woof', $page->Author);

        \Versioned::reading_stage('Live');
        $page = DogPage::get()->first();
        $this->assertNotNull($page);
        $this->assertEquals('WOOF', $page->Title);
        $this->assertEquals('Woof Woof', $page->Author);

        \Versioned::reading_stage($currentStage);
    }

    /**
     *
     */
    public function testBatchWrite_DifferentClasses_WritesObjects()
    {
        $dog = new Dog();
        $dog->Name = 'Snuffins';
        $dog->Color = 'Red';

        $cat = new Cat();
        $cat->Name = 'Puff daddy';
        $cat->HasClaws = true;

        $batch = new \Batch();
        $batch->write(array($dog, $cat));

        $this->assertTrue($dog->exists());
        $this->assertTrue($cat->exists());

        $dog = Dog::get()->first();
        $this->assertEquals('Snuffins', $dog->Name);
        $this->assertEquals('Red', $dog->Color);

        $cat = Cat::get()->first();
        $this->assertEquals('Puff daddy', $cat->Name);
        $this->assertEquals(1, $cat->HasClaws);
    }

//    public static function tearDownAfterClass()
//    {
//        parent::tearDownAfterClass();
//        \SapphireTest::delete_all_temp_dbs();
//    }
}
