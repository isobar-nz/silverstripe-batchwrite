<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\Batch;
use LittleGiant\BatchWrite\Helpers\OnAfterExists;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\ORM\ValidationException;

/**
 * Class OnAfterExistsTest
 * @package LittleGiant\BatchWrite\Tests
 */
class OnAfterExistsTest extends BaseTest
{
    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_OneCondition_CalledBack()
    {
        $dog = new Dog();
        $dog->Name = 'Johnny';

        $owner = new Human();
        $owner->Name = 'Bob';

        $afterExists = new OnAfterExists(function () use ($dog) {
            $dog->write();
        });

        $afterExists->addCondition($owner, function ($owner) use ($dog) {
            $dog->OwnerID = $owner->ID;
        });

        $owner->write();

        $this->assertTrue($dog->exists());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws ValidationException
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

        $afterExists = new OnAfterExists(function () use ($dog) {
            $dog->write();
        });

        $afterExists->addCondition($owner1, function ($owner) use ($dog) {
            $dog->Name .= ' ' . $owner->Name;
            $dog->OwnerID = $owner->ID;
        });

        $afterExists->addCondition($owner2, function ($owner) use ($dog) {
            $dog->Name .= ' ' . $owner->Name;
            $dog->OwnerID = $owner->ID;
        });

        $afterExists->addCondition($cat, function ($cat) use ($dog) {
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

        $children = [];
        for ($i = 0; $i < 5; $i++) {
            $child = new Child();
            $child->Name = 'Soldier #' . $i;
            $children[] = $child;
        }

        $batch = new Batch();

        $afterExists = new OnAfterExists(function () use ($batch, $parent, $children) {
            $sets = [];
            foreach ($children as $child) {
                $sets[] = [$parent, 'Children', $child];
            }
            $batch->writeManyMany($sets);
        });

        $afterExists->addCondition($parent);
        $afterExists->addCondition($children);

        $batch->write([$parent]);
        $batch->write($children);

        /** @var Human $parent */
        $parent = Human::get()->first();
        $this->assertEquals(5, $parent->Children()->Count());
    }
}
