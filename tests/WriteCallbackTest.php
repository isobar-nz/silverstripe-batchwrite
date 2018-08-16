<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Tests\DataObjects\Dog;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;
use SilverStripe\ORM\ValidationException;

/**
 * Class WriteCallbackTest
 * @package LittleGiant\BatchWrite\Tests
 */
class WriteCallbackTest extends BaseTest
{
    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnAfterWriteCallback_CallbackCalled()
    {
        $dog = Dog::create();
        $dog->Name = $this->faker->firstName;

        $owner = Human::create();
        $owner->Name = $this->faker->name;

        $owner->onAfterWriteCallback(function ($owner) use ($dog) {
            $dog->OwnerID = $owner->ID;
            $dog->write();
        });

        $owner->write();

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog->exists());
        $this->assertCount(1, Human::get());
        $this->assertCount(1, Dog::get());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnBeforeWriteCallback_CallbackCalled()
    {
        $dog = Dog::create();
        $dog->Name = $this->faker->firstName;

        $owner = Human::create();
        $owner->Name = $this->faker->name;
        $owner->write();

        $dog->onBeforeWriteCallback(function ($dog) use ($owner) {
            $dog->OwnerID = $owner->ID;
        });

        $dog->write();

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog->exists());
        $this->assertCount(1, Human::get());
        $this->assertCount(1, Dog::get());
        $this->assertEquals($owner->ID, $dog->OwnerID);
    }

    /**
     * @throws ValidationException
     * @throws null
     */
    public function testCallback_SetOnAfterExistsCallback_CallbackCalled()
    {
        $dog1 = Dog::create();
        $dog1->Name = $this->faker->firstName;

        $dog2 = Dog::create();
        $dog2->Name = $this->faker->firstName;

        $owner = Human::create();
        $owner->Name = $this->faker->name;
        $owner->write();

        $owner->onAfterExistsCallback(function ($owner) use ($dog1) {
            $dog1->OwnerID = $owner->ID;
            $dog1->write();
        });

        $owner->write();

        $owner->onAfterExistsCallback(function ($owner) use ($dog2) {
            $dog2->OwnerID = $owner->ID;
            $dog2->write();
        });

        $this->assertTrue($owner->exists());
        $this->assertTrue($dog1->exists());
        $this->assertTrue($dog2->exists());
        $this->assertCount(1, Human::get());
        $this->assertCount(2, Dog::get());
        $this->assertEquals($owner->ID, $dog1->OwnerID);
        $this->assertEquals($owner->ID, $dog2->OwnerID);
    }
}
