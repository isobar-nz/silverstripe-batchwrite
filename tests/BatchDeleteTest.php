<?php

namespace BatchWrite\Tests;

/**
 * Class BatchDeleteTest
 * @package BatchWrite\Tests
 */
/**
 * Class BatchDeleteTest
 * @package BatchWrite\Tests
 */
class BatchDeleteTest extends \SapphireTest
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
     * BatchDeleteTest constructor.
     */
    public function __construct()
    {
        $this->setUpOnce();
    }

    /**
     * @throws \ValidationException
     * @throws null
     */
    public function testBranchDelete_DeleteManyObjects_ObjectsDeleted()
    {
        $objects = array();
        for ($i = 0; $i < 100; $i++) {
            $human = new Human();
            $human->Name = 'Proud Owner ' . $i;
            $human->write();

            $dog = new Dog();
            $dog->Name = 'Pup ' . $i;
            $dog->Color = 'Fifty Shade No. ' . $i;
            $dog->Owner($human);
            $dog->write();

            $objects[] = $human;
            $objects[] = $dog;
        }

        $batch = new \Batch();
        $batch->delete($objects);

        $this->assertEquals(0, Dog::get()->Count());
        $this->assertEquals(0, Human::get()->Count());
    }

    /**
     * @throws \ValidationException
     * @throws null
     */
    public function testBranchDeleteIDs_DeleteManyIDs_ObjectsDeleted()
    {
        $className = '';
        $ids = array();
        for ($i = 0; $i < 100; $i++) {
            $dog = new Dog();
            $dog->Name = 'Pup ' . $i;
            $dog->Color = 'Fifty Shade No. ' . $i;
            $dog->write();
            $className = $dog->ClassName;
            $ids[] = $dog->ID;
        }

        $batch = new \Batch();
        $batch->deleteIDs($className, $ids);

        $this->assertEquals(0, Dog::get()->Count());
    }

    /**
     *
     */
    public function testBatchDelete_VersionedObject_ObjectsDeleted()
    {
        $pages = array();
        for ($i = 0; $i < 100; $i++) {
            $page = new DogPage();
            $page->Title = 'Hero Dog ' . $i;
            $page->writeToStage('Stage');
            $page->publish('Stage', 'Live');
            $pages[] = $page;
        }

        $batch = new \Batch();

        $currentStage = \Versioned::current_stage();
        \Versioned::reading_stage('Live');

        $this->assertEquals(100, DogPage::get()->Count());

        $batch->deleteFromStage($pages, 'Live');

        $this->assertEquals(0, DogPage::get()->Count());

        \Versioned::reading_stage('Stage');

        $this->assertEquals(100, DogPage::get()->Count());

        $batch->deleteFromStage($pages, 'Stage');

        $this->assertEquals(0, DogPage::get()->Count());

        \Versioned::reading_stage($currentStage);
    }
//
//    public static function tearDownAfterClass()
//    {
//        parent::tearDownAfterClass();
//        \SapphireTest::delete_all_temp_dbs();
//    }
}
