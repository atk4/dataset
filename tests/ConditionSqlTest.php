<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class ConditionSqlTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testBasic()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $mm = clone $m;
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertSame('Sue', $mmm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm->tryLoad(2);
        $this->assertNull($mm->get('name'));

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","name","gender" from "user" where "gender" = :a',
                $mm->action('select')->render()
            );
        }

        $mm = clone $m;
        $mm->withId(2); // = addCondition(id, 2)
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));
    }

    public function testEntityReloadWithDifferentIdException()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $mm = clone $m;
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $this->expectException(\Atk4\Data\Exception::class);
        $this->expectExceptionMessageMatches('~different~');
        $mm->tryLoad(2);
    }

    public function testNull()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
                3 => ['id' => 3, 'name' => 'Null1', 'gender' => null],
                4 => ['id' => 4, 'name' => 'Null2', 'gender' => null],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $m->addCondition('gender', null);

        $nullCount = 0;
        foreach ($m as $user) {
            $this->assertNull($user->get('gender'));
            $this->assertStringContainsString('Null', $user->get('name'));

            ++$nullCount;
        }

        $this->assertSame(2, $nullCount);
    }

    public function testOperations()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $mm = clone $m;
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertSame('Sue', $mmm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm->tryLoad(2);
        $this->assertNull($mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', '!=', 'M');
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', '>', 1);
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', 'in', [1, 3]);
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm->tryLoad(2);
        $this->assertNull($mm->get('name'));
    }

    public function testExpressions1()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $mm = clone $m;
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertSame('Sue', $mmm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getField('id')]));
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));
    }

    public function testExpressions2()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender', 'surname']);

        $mm = clone $m;
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertSame('Sue', $mmm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), $m->getField('surname'));
        $mm->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm->tryLoad(2);
        $this->assertNull($mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), '!=', $m->getField('surname'));
        $mm->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm->tryLoad(2);
        $this->assertNull($mm->get('name'));
    }

    public function testExpressionJoin()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F', 'contact_id' => 2],
                3 => ['id' => 3, 'name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123 smiths'],
                2 => ['id' => 2, 'contact_phone' => '+321 sues'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender', 'surname']);

        $m->join('contact')->addField('contact_phone');

        $mm = clone $m;
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $this->assertSame('+123 smiths', $mmm->get('contact_phone'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertSame('Sue', $mmm->get('name'));
        $this->assertSame('+321 sues', $mmm->get('contact_phone'));
        $mmm = (clone $mm)->tryLoad(3);
        $this->assertSame('Peter', $mmm->get('name'));
        $this->assertSame('+123 smiths', $mmm->get('contact_phone'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm->tryLoad(1);
        $this->assertFalse($mm->loaded());
        $mm->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));
        $this->assertSame('+321 sues', $mm->get('contact_phone'));
        $mm->tryLoad(3);
        $this->assertFalse($mm->loaded());

        $mm = clone $m;
        $mm->addCondition($mm->expr('\'+123 smiths\' = [contact_phone]'));
        $mmm = (clone $mm)->tryLoad(1);
        $this->assertSame('John', $mmm->get('name'));
        $this->assertSame('+123 smiths', $mmm->get('contact_phone'));
        $mmm = (clone $mm)->tryLoad(2);
        $this->assertNull($mmm->get('name'));
        $this->assertNull($mmm->get('contact_phone'));
        $mmm = (clone $mm)->tryLoad(3);
        $this->assertSame('Peter', $mmm->get('name'));
        $this->assertSame('+123 smiths', $mmm->get('contact_phone'));
    }

    public function testArrayCondition()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Johhny'],
                3 => ['id' => 3, 'name' => 'Mary'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', ['John', 'Doe']);
        $this->assertSame(1, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', 'in', ['Johhny', 'Doe', 'Mary']);
        $this->assertSame(2, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', []); // this should not fail, always should be false
        $this->assertSame(0, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', 'not in', []); // this should not fail, always should be true
        $this->assertSame(3, count($m->export()));
    }

    public function testDateCondition()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->tryLoadBy('date', new \DateTime('08-12-1982'));
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testDateCondition2()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->addCondition('date', new \DateTime('08-12-1982'));
        $m->loadAny();
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testDateConditionFailure()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $this->expectException(\Atk4\Dsql\Exception::class);
        $m->tryLoadBy('name', new \DateTime('08-12-1982'));
    }

    public function testOrConditions()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = (new Model($this->db, 'user'))->addFields(['name']);

        $u->addCondition(Model\Scope::createOr(
            ['name', 'John'],
            ['name', 'Peter'],
        ));

        $this->assertEquals(2, $u->action('count')->getOne());

        $u->addCondition(Model\Scope::createOr(
            ['name', 'Peter'],
            ['name', 'Joe'],
        ));
        $this->assertEquals(1, $u->action('count')->getOne());
    }

    /**
     * Test loadBy and tryLoadBy.
     * They should set only temporary condition.
     */
    public function testLoadBy()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = (new Model($this->db, 'user'))->addFields(['name']);

        $u->loadBy('name', 'John');
        $this->assertTrue($u->scope()->isEmpty());
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value

        $u->tryLoadBy('name', 'John');
        $this->assertTrue($u->scope()->isEmpty());
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value
    }

    /**
     * Test LIKE condition.
     */
    public function testLikeCondition()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL does not support "column LIKE variable" syntax');
        }

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'active' => 1, 'created' => '2020-01-01 15:00:30'],
                2 => ['id' => 2, 'name' => 'Peter', 'active' => 0, 'created' => '2019-05-20 12:13:14'],
                3 => ['id' => 3, 'name' => 'Joe', 'active' => 1, 'created' => '2019-07-15 09:55:05'],
            ],
        ]);

        $u = new Model($this->db, 'user');
        $u->addField('name', ['type' => 'string']);
        $u->addField('active', ['type' => 'boolean']);
        $u->addField('created', ['type' => 'datetime']);

        $t = (clone $u)->addCondition('created', 'like', '%19%');
        $this->assertSame(2, count($t->export())); // only year 2019 records

        $t = (clone $u)->addCondition('active', 'like', '%1%');
        $this->assertSame(2, count($t->export())); // only active records

        $t = (clone $u)->addCondition('active', 'like', '%0%');
        $this->assertSame(1, count($t->export())); // only inactive records

        $t = (clone $u)->addCondition('active', 'like', '%999%');
        $this->assertSame(0, count($t->export())); // bad value, so it will not match anything

        $t = (clone $u)->addCondition('active', 'like', '%ABC%');
        $this->assertSame(0, count($t->export())); // bad value, so it will not match anything
    }
}
