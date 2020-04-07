<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Exception;
use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionTest extends AtkPhpunit\TestCase
{
    public function testException1()
    {
        // not existing field in condition
        $m = new Model();
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->addCondition('last_name', 'Smith');
    }

    public function testBasicDiscrimination()
    {
        $m = new Model();
        $m->addField('name');

        $m->addField('gender', ['enum' => ['M', 'F']]);
        $m->addField('foo');

        $m->addCondition('gender', 'M');

        $this->assertEquals(1, count($m->scope()->getActiveComponents()));

        $m->addCondition('gender', 'F');

        $this->assertEquals(2, count($m->scope()->getActiveComponents()));

        $m->addCondition([['gender', 'F'], ['foo', 'bar']]);
        $this->assertEquals(3, count($m->scope()->getActiveComponents()));
    }

    public function testEditableAfterCondition()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', 'M');

        $this->assertTrue($m->getField('gender')->system);
        $this->assertFalse($m->getField('gender')->isEditable());
    }

    public function testEditableHasOne()
    {
        $gender = new Model();
        $gender->addField('name');

        $m = new Model();
        $m->addField('name');
        $m->hasOne('gender_id', $gender);

        $this->assertFalse($m->getField('gender_id')->system);
        $this->assertTrue($m->getField('gender_id')->isEditable());
    }
}
