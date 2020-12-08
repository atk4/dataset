<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class Model_Rate extends \Atk4\Data\Model
{
    public $table = 'rate';

    protected function init(): void
    {
        parent::init();
        $this->addField('dat');
        $this->addField('bid');
        $this->addField('ask');
    }
}
class Model_Item extends \Atk4\Data\Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
        $this->hasOne('parent_item_id', ['model' => [self::class]])
            ->addTitle();
    }
}
class Model_Item2 extends \Atk4\Data\Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', new self())
            ->addTitle();
    }
}
class Model_Item3 extends \Atk4\Data\Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();

        $m = new self();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', [$m, 'table_alias' => 'parent'])
            ->withTitle();

        $this->hasMany('Child', [$m, 'their_field' => 'parent_item_id', 'table_alias' => 'child'])
            ->addField('child_age', ['aggregate' => 'sum', 'field' => 'age']);
    }
}

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class RandomTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testRate()
    {
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model_Rate($db);

        $this->assertEquals(2, $m->action('count')->getOne());
    }

    public function testTitleImport()
    {
        $this->setDb([
            'user' => [
                '_' => ['name' => 'John', 'salary' => 29],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', ['salary', 'default' => 10]]);

        $m->import([['name' => 'Peter'], ['name' => 'Steve', 'salary' => 30]]);
        $m->insert(['name' => 'Sue']);
        $m->insert(['name' => 'John', 'salary' => 40]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'Peter', 'salary' => 10],
                2 => ['id' => 2, 'name' => 'Steve', 'salary' => 30],
                3 => ['id' => 3, 'name' => 'Sue', 'salary' => 10],
                4 => ['id' => 4, 'name' => 'John', 'salary' => 40],
            ],
        ], $this->getDb());
    }

    public function testAddFields()
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'login' => 'john@example.com'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', 'login'], ['default' => 'unknown']);

        $m->insert(['name' => 'Peter']);
        $m->insert([]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'login' => 'john@example.com'],
                2 => ['id' => 2, 'name' => 'Peter', 'login' => 'unknown'],
                3 => ['id' => 3, 'name' => 'unknown', 'login' => 'unknown'],
            ],
        ], $this->getDb());
    }

    public function testAddFields2()
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name'], ['default' => 'anonymous']);
        $m->addFields([
            'last_name',
            'login' => ['default' => 'unknown'],
            'salary' => ['type' => 'money', CustomField::class, 'default' => 100],
            ['tax', CustomField::class, 'type' => 'money', 'default' => 20],
            'vat' => new CustomField(['type' => 'money', 'default' => 15]),
        ]);

        $m->insert([]);

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ['id' => 2, 'name' => 'anonymous', 'last_name' => null, 'login' => 'unknown', 'salary' => 100, 'tax' => 20, 'vat' => 15],
        ], $m->export());

        $m->load(2);
        $this->assertTrue(is_float($m->get('salary')));
        $this->assertTrue(is_float($m->get('tax')));
        $this->assertTrue(is_float($m->get('vat')));
    }

    public function testSameTable()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
                3 => ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item($db, 'item');

        $this->assertSame(
            ['id' => '3', 'name' => 'Smith', 'parent_item_id' => '2', 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable2()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Sue'],
                3 => ['id' => 3, 'name' => 'Smith'],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item2($db, 'item');

        $this->assertSame(
            ['id' => '3', 'name' => 'Smith', 'parent_item_id' => '2', 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable3()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age' => 18],
                2 => ['id' => 2, 'name' => 'Sue', 'age' => 20],
                3 => ['id' => 3, 'name' => 'Smith', 'age' => 24],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item3($db, 'item');

        $this->assertEquals(
            ['id' => '2', 'name' => 'Sue', 'parent_item_id' => 1, 'parent_item' => 'John', 'age' => '20', 'child_age' => 24],
            $m->load(2)->get()
        );

        $this->assertEquals(1, $m->load(2)->ref('Child', ['table_alias' => 'pp'])->action('count')->getOne());
        $this->assertSame('John', $m->load(2)->ref('parent_item_id', ['table_alias' => 'pp'])->get('name'));
    }

    public function testUpdateCondition()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($db, 'item');
        $m->addField('name');
        $m->load(2);

        $m->onHook(Persistence\Sql::HOOK_AFTER_UPDATE_QUERY, static function ($m, $update, $st) {
            // we can use afterUpdate to make sure that record was updated

            if (!$st->rowCount()) {
                throw (new \Atk4\Core\Exception('Update didn\'t affect any records'))
                    ->addMoreInfo('query', $update->getDebugQuery())
                    ->addMoreInfo('statement', $st)
                    ->addMoreInfo('model', $m)
                    ->addMoreInfo('conditions', $m->conditions);
            }
        });

        $this->assertSame('Sue', $m->get('name'));

        $dbData = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
        ];
        $this->setDb($dbData);

        $m->set('name', 'Peter');

        try {
            $m->save();
            $e = null;
        } catch (\Exception $e) {
        }

        $this->assertNotNull($e);
        $this->assertEquals($dbData, $this->getDb());
    }

    public function testHookBreakers()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($db, 'user');
        $m->addField('name');

        $m->onHook(Model::HOOK_BEFORE_SAVE, static function ($m) {
            $m->breakHook(false);
        });

        $m->onHook(Model::HOOK_BEFORE_LOAD, static function ($m, $id) {
            $m->data = ['name' => 'rec #' . $id];
            $m->setId($id);
            $m->breakHook(false);
        });

        $m->onHook(Model::HOOK_BEFORE_DELETE, static function ($m, $id) {
            $m->unload();
            $m->breakHook(false);
        });

        $m->set('name', 'john');
        $m->save();

        $this->assertSame('rec #3', $m->load(3)->get('name'));

        $m->delete();
    }

    public function testIssue220()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model_Item($db);

        $this->expectException(Exception::class);
        $m->hasOne('foo', ['model' => [Model_Item::class]])
            ->addTitle(); // field foo already exists, so we can't add title with same name
    }

    public function testNonSqlFieldClass()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4, 'x1' => 'y1', 'x2' => 'y2'],
            ],
        ]);

        $m = new Model_Rate($db);
        $m->addField('x1', new \Atk4\Data\FieldSql());
        $m->addField('x2', new \Atk4\Data\Field());
        $m->load(1);

        $this->assertEquals(3.4, $m->get('bid'));
        $this->assertSame('y1', $m->get('x1'));
        $this->assertSame('y2', $m->get('x2'));
    }

    public function testModelCaption()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');

        // caption is not set, so generate it from class name \Atk4\Data\Model
        $this->assertSame('Atk 4 Data Model', $m->getModelCaption());

        // caption is set
        $m->caption = 'test';
        $this->assertSame('test', $m->getModelCaption());
    }

    public function testGetTitle()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
            ],
        ]);

        $m = new Model_Item($db, 'item');

        // default title_field = name
        $this->assertNull($m->getTitle()); // not loaded model returns null
        $this->assertSame([1 => 'John', 2 => 'Sue'], $m->getTitles()); // all titles

        $m->load(2);
        $this->assertSame('Sue', $m->getTitle()); // loaded returns title_field value
        $this->assertSame([1 => 'John', 2 => 'Sue'], $m->getTitles()); // all titles

        // set custom title_field
        $m->title_field = 'parent_item_id';
        $this->assertEquals(1, $m->getTitle()); // returns parent_item_id value

        // set custom title_field as title_field from linked model
        $m->title_field = 'parent_item';
        $this->assertSame('John', $m->getTitle()); // returns parent record title_field

        // no title_field set - return id value
        $m->title_field = null;
        $this->assertEquals(2, $m->getTitle()); // loaded returns id value

        // expression as title field
        $m->addExpression('my_name', '[id]');
        $m->title_field = 'my_name';
        $m->load(2);
        $this->assertEquals(2, $m->getTitle()); // loaded returns id value
        $this->assertEquals([1 => 1, 2 => 2], $m->getTitles()); // all titles (my_name)
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $this->setDb([
            'user' => [
                2 => ['code' => 10, 'name' => 'John'],
                5 => ['code' => 20, 'name' => 'Sarah'],
            ],
        ]);

        // model without id field
        $m1 = new Model($this->db, ['table' => 'user', 'id_field' => false]);
        $m1->addField('code');
        $m1->addField('name');

        // model with id field
        $m2 = new Model($this->db, 'user');
        $m2->addField('code');
        $m2->addField('name');

        // normal export
        $this->assertEquals([
            0 => ['code' => 10, 'name' => 'John'],
            1 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export());

        $this->assertEquals([
            0 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            1 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export());

        // export fields explicitly set
        $this->assertSame([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m1->export(['name']));

        $this->assertSame([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m2->export(['name']));

        // key field explicitly set
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(null, 'code'));

        $this->assertEquals([
            10 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            20 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export(null, 'code'));

        // field names and key field explicitly set
        $this->assertSame([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m1->export(['name'], 'code'));

        $this->assertSame([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m2->export(['name'], 'code'));

        // field names include key field
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(['code', 'name'], 'code'));

        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m2->export(['code', 'name'], 'code'));
    }

    public function testNewInstance()
    {
        // model without persistence
        $m = new Model(['table' => 'order']);
        $a = $m->newInstance();
        $this->assertFalse(isset($a->persistence));

        // model with persistence
        $db = new Persistence();
        $m = new Model($db, ['table' => 'order']);
        $a = $m->newInstance();
        $this->assertTrue(isset($a->persistence));
    }

    public function testTableNameDots()
    {
        $d = new Model($this->db, 'db2.doc');
        $d->addField('name');

        $m = new Model($this->db, 'db1.user');
        $m->addField('name');

        $d->hasOne('user_id', $m)->addTitle();
        $m->hasMany('Documents', $d);

        $d->addCondition('user', 'Sarah');

        $this->assertSameSql(
            'select "id","name","user_id",(select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") "user" from "db2"."doc" where (select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") = :a',
            $d->action('select')->render()
        );
    }
}

class CustomField extends \Atk4\Data\Field
{
}
