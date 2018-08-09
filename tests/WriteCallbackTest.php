<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Tests\DataObjects\Animal;
use LittleGiant\BatchWrite\Tests\DataObjects\Batman;
use LittleGiant\BatchWrite\Tests\DataObjects\Cat;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\DogPage;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;

/**
 * Class WriteCallbackTest
 * @package LittleGiant\BatchWrite\Tests
 */
class WriteCallbackTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected $extraDataObjects = array(
        Animal::class,
        Batman::class,
        Cat::class,
        Child::class,
        Child::class,
        Dog::class,
        DogPage::class,
        Human::class,
    );

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnAfterWriteCallback_CallbackCalled()
    {
        $dog = new Dog();
        $dog->Name = 'Jim bob';

        $owner = new Human();
        $owner->Name = 'Hilly Stewart';

        $owner->onAfterWriteCallback(function ($owner) use ($dog) {
            $dog->OwnerID = $owner->ID;
            $dog->write();
        });

        $owner->write();

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog->exists());
        $this->assertEquals(1, Human::get()->Count());
        $this->assertEquals(1, Dog::get()->Count());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnBeforeWriteCallback_CallbackCalled()
    {
        $dog = new Dog();
        $dog->Name = 'Jim bob';

        $owner = new Human();
        $owner->Name = 'Hilly Stewart';
        $owner->write();

        $dog->onBeforeWriteCallback(function ($dog) use ($owner) {
            $dog->OwnerID = $owner->ID;
        });

        $dog->write();

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog->exists());
        $this->assertEquals(1, Human::get()->Count());
        $this->assertEquals(1, Dog::get()->Count());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnAfterExistsCallback_CallbackCalled()
    {
        $dog1 = new Dog();
        $dog1->Name = 'Jim bob';

        $dog2 = new Dog();
        $dog2->Name = 'Super Dog';

        $owner = new Human();
        $owner->Name = 'Hilly Stewart';
        $owner->write();

        $owner->onAfterExistsCallback(function ($owner) use ($dog1)  {
            $dog1->OwnerID = $owner->ID;
            $dog1->write();
        });

        $owner->write();

        $owner->onAfterExistsCallback(function ($owner) use ($dog2)  {
            $dog2->OwnerID = $owner->ID;
            $dog2->write();
        });

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog1->exists());
        $this->assertTrue($dog2->exists());
        $this->assertEquals(1, Human::get()->Count());
        $this->assertEquals(2, Dog::get()->Count());
        $this->assertEquals($owner->ID, $dog1->OwnerID);
        $this->assertEquals($owner->ID, $dog2->OwnerID);
    }
}
