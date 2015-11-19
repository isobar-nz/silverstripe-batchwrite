<?php

namespace BatchWrite\Tests;

/**
 * Class OnAfterExistsTest
 * @package BatchWrite\Tests
 */
/**
 * Class OnAfterExistsTest
 * @package BatchWrite\Tests
 */
class OnAfterExistsTest extends \SapphireTest
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
     * OnAfterExistsTest constructor.
     */
    public function __construct()
    {
        $this->setUpOnce();
    }

    /**
     * @throws \ValidationException
     * @throws null
     */
    public function testCallback_OneCondition_CalledBack()
    {
        $dog = new Dog();
        $dog->Name = 'Johnny';

        $owner = new Human();
        $owner->Name = 'Bob';

        $afterExists = new \OnAfterExists(function () use ($dog) {
            $dog->write();
        });

        $afterExists->addCondition($owner, function ($owner) use($dog) {
            $dog->OwnerID = $owner->ID;
        });

        $owner->write();

        $this->assertTrue($dog->exists());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws \ValidationException
     * @throws null
     */
    public function testCallback_ManyConditions_CalledBack()
    {
        $dog = new Dog();
        $dog->Name = 'Johnny';

        $owner1 = new Human();
        $owner1->Name = 'Bob';

        $owner2 = new Human();
        $owner2->Name = 'Wot';

        $cat = new Cat();
        $cat->Name = 'Agnis';

        $afterExists = new \OnAfterExists(function () use ($dog) {
            $dog->write();
        });

        $afterExists->addCondition($owner1, function ($owner) use($dog) {
            $dog->Name .= ' ' . $owner->Name;
            $dog->OwnerID = $owner->ID;
        });

        $afterExists->addCondition($owner2, function ($owner) use($dog) {
            $dog->Name .= ' ' . $owner->Name;
            $dog->OwnerID = $owner->ID;
        });

        $afterExists->addCondition($cat, function ($cat) use($dog) {
            $dog->Name .= ' ' . $cat->Name;
        });

        $owner1->write();

        $this->assertFalse($dog->exists());

        $owner2->write();

        $this->assertFalse($dog->exists());

        $cat->write();

        $this->assertTrue($dog->exists());
        $this->assertEquals($owner2->ID, $dog->OwnerID);
        $this->assertEquals('Johnny Bob Wot Agnis', $dog->Name);
    }

    /**
     *
     */
    public function testOnAfterExists_ArrayCondition_CalledBack()
    {
        $parent = new Human();
        $parent->Name = 'Bob';

        $children = array();
        for ($i = 0; $i < 5; $i++) {
            $child = new Child();
            $child->Name = 'Soldier #' . $i;
            $children[] = $child;
        }

        $batch = new \Batch();

        $afterExists = new \OnAfterExists(function () use($batch, $parent, $children) {
            $sets = array();
            foreach ($children as $child) {
                $sets[] = array($parent, 'Children', $child);
            }
            $batch->writeManyMany($sets);
        });

        $afterExists->addCondition($parent);
        $afterExists->addCondition($children);

        $batch->write(array($parent));
        $batch->write($children);

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
