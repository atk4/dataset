<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Female as Female;
use Atk4\Data\Tests\Model\Male as Male;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class PersistentArrayTest extends AtkPhpunit\TestCase
{
    private function getInternalPersistenceData(Persistence\Array_ $db): array
    {
        return $this->getProtected($db, 'data');
    }

    /**
     * Test constructor.
     */
    public function testLoadArray()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm->unload();
        $this->assertFalse($mm->loaded());

        $mm->tryLoadAny();
        $this->assertTrue($mm->loaded());

        $mm = (clone $m)->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testSaveAs()
    {
        $p = new Persistence\Array_([
            'person' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ]);

        $m = new Male($p);
        $m->load(1);
        $m->saveAs(Female::class);
        $m->delete();

        $this->assertEquals([
            'person' => [
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
                3 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testSaveAndUnload()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ]);
        $m = new Male($p, 'user');

        $m->load(1);
        $this->assertTrue($m->loaded());
        $m->set('gender', 'F');
        $m->saveAndUnload();
        $this->assertFalse($m->loaded());

        $m = new Female($p, 'user');
        $m->load(1);
        $this->assertTrue($m->loaded());

        $this->assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testUpdateArray()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->load(1);
        $mm->set('name', 'Peter');
        $mm->save();

        $mm = (clone $m)->load(2);
        $mm->set('surname', 'Smith');
        $mm->save();
        $mm->set('surname', 'QQ');
        $mm->save();

        $this->assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $this->getInternalPersistenceData($p));

        $m->unload();
        $m->setMulti(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testInsert()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));

        $this->assertSame(3, $p->lastInsertID());
    }

    public function testIterator()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row->get('name');
        }

        $this->assertSame('JohnSarah', $output);
    }

    /**
     * Test short format.
     */
    public function testShortFormat()
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    /**
     * Some persistences don't support tryLoad() method.
     */
    public function testTryLoadNotSupportedException()
    {
        $m = new Model(new Persistence());
        $this->expectException(Exception::class);
        $m->tryLoad(1);
    }

    /**
     * Some persistences don't support loadAny() method.
     */
    public function testLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $this->expectException(Exception::class);
        $m->loadAny();
    }

    /**
     * Some persistences don't support tryLoadAny() method.
     */
    public function testTryLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $this->expectException(Exception::class);
        $m->tryLoadAny();
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            2 => ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            1 => ['surname' => 'Smith'],
            2 => ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    /**
     * Test Model->action('count').
     */
    public function testActionCount()
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(2, $m->action('count')->getOne());
    }

    /**
     * Test Model->action('field').
     */
    public function testActionField()
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(2, $m->action('count')->getOne());

        // use alias as array key if it is set
        $q = $m->action('field', ['name', 'alias' => 'first_name']);
        $this->assertSame(['first_name' => 'John'], $q);

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['name']);
        $this->assertSame(['name' => 'John'], $q);
    }

    /**
     * Test Model->addCondition operator LIKE.
     */
    public function testLike()
    {
        $dbData = ['countries' => [
            1 => ['id' => 1, 'name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['id' => 2, 'name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['id' => 3, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['id' => 4, 'name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['id' => 5, 'name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['id' => 6, 'name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['id' => 7, 'name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['id' => 8, 'name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['id' => 9, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
            10 => ['id' => 10, 'code' => null, 'country' => 'Germany', 'active' => 1],
        ]];

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, 'countries');
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        // if no condition we should get all the data back
        $iterator = $m->action('select');
        $result = $m->persistence->applyScope($m, $iterator);
        $this->assertInstanceOf(\Atk4\Data\Action\Iterator::class, $result);
        $m->unload();
        unset($iterator);
        unset($result);

        // case : str%
        $m->addCondition('country', 'LIKE', 'La%');
        $result = $m->action('select')->get();
        $this->assertSame(3, count($result));
        $this->assertSame($dbData['countries'][3], $result[3]);
        $this->assertSame($dbData['countries'][7], $result[7]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : str% NOT LIKE
        $m->scope()->clear();
        $m->addCondition('country', 'NOT LIKE', 'La%');
        $result = $m->action('select')->get();
        $this->assertSame(7, count($m->export()));
        $this->assertSame($dbData['countries'][1], $result[1]);
        $this->assertSame($dbData['countries'][2], $result[2]);
        $this->assertSame($dbData['countries'][4], $result[4]);
        $this->assertSame($dbData['countries'][5], $result[5]);
        $this->assertSame($dbData['countries'][6], $result[6]);
        $this->assertSame($dbData['countries'][8], $result[8]);
        unset($result);

        // case : %str
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%ia');
        $result = $m->action('select')->get();
        $this->assertSame(4, count($result));
        $this->assertSame($dbData['countries'][3], $result[3]);
        $this->assertSame($dbData['countries'][7], $result[7]);
        $this->assertSame($dbData['countries'][8], $result[8]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : %str%
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%a%');
        $result = $m->action('select')->get();
        $this->assertSame(8, count($result));
        $this->assertSame($dbData['countries'][1], $result[1]);
        $this->assertSame($dbData['countries'][2], $result[2]);
        $this->assertSame($dbData['countries'][3], $result[3]);
        $this->assertSame($dbData['countries'][6], $result[6]);
        $this->assertSame($dbData['countries'][7], $result[7]);
        $this->assertSame($dbData['countries'][8], $result[8]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : boolean field
        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '0');
        $this->assertSame(4, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '1');
        $this->assertSame(6, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%0%');
        $this->assertSame(4, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%1%');
        $this->assertSame(6, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%999%');
        $this->assertSame(0, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%ABC%');
        $this->assertSame(0, count($m->export()));

        // null value
        $m->scope()->clear();
        $m->addCondition('code', '=', null);
        $this->assertSame(1, count($m->export()));

        $m->scope()->clear();
        $m->addCondition('code', '!=', null);
        $this->assertSame(9, count($m->export()));
    }

    /**
     * Test Model->addCondition operator REGEXP.
     */
    public function testConditions()
    {
        $dbData = ['countries' => [
            1 => ['id' => 1, 'name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['id' => 2, 'name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['id' => 3, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['id' => 4, 'name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['id' => 5, 'name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['id' => 6, 'name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['id' => 7, 'name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['id' => 8, 'name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['id' => 9, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
        ]];

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, 'countries');
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        // if no condition we should get all the data back
        $iterator = $m->action('select');
        $result = $m->persistence->applyScope($m, $iterator);
        $this->assertInstanceOf(\Atk4\Data\Action\Iterator::class, $result);
        $m->unload();
        unset($iterator);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('country', 'REGEXP', 'Ireland|UK');
        $result = $m->action('select')->get();
        $this->assertSame(5, count($result));
        $this->assertSame($dbData['countries'][1], $result[1]);
        $this->assertSame($dbData['countries'][2], $result[2]);
        $this->assertSame($dbData['countries'][4], $result[4]);
        $this->assertSame($dbData['countries'][5], $result[5]);
        $this->assertSame($dbData['countries'][6], $result[6]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('country', 'NOT REGEXP', 'Ireland|UK|Latvia');
        $result = $m->action('select')->get();
        $this->assertSame(1, count($result));
        $this->assertSame($dbData['countries'][8], $result[8]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', '>', 18);
        $result = $m->action('select')->get();
        $this->assertSame(1, count($result));
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', '>=', 18);
        $result = $m->action('select')->get();
        $this->assertSame(2, count($result));
        $this->assertSame($dbData['countries'][8], $result[8]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', '<', 12);
        $result = $m->action('select')->get();
        $this->assertSame(1, count($result));
        $this->assertSame($dbData['countries'][1], $result[1]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', '<=', 12);
        $result = $m->action('select')->get();
        $this->assertSame(2, count($result));
        $this->assertSame($dbData['countries'][1], $result[1]);
        $this->assertSame($dbData['countries'][2], $result[2]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', [11, 12]);
        $result = $m->action('select')->get();
        $this->assertSame(2, count($result));
        $this->assertSame($dbData['countries'][1], $result[1]);
        $this->assertSame($dbData['countries'][2], $result[2]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', 'IN', []);
        $result = $m->action('select')->get();
        $this->assertSame(0, count($result));
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', 'NOT IN', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->get();
        $this->assertSame(2, count($result));
        $this->assertSame($dbData['countries'][8], $result[8]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        $m->scope()->clear();
        $m->addCondition('code', '!=', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->get();
        $this->assertSame(2, count($result));
        $this->assertSame($dbData['countries'][8], $result[8]);
        $this->assertSame($dbData['countries'][9], $result[9]);
        unset($result);
        $m->unload();
    }

    public function testAggregates()
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['id' => 1, 'number' => 'ABC9', 'items' => 11, 'active' => 1],
            2 => ['id' => 2, 'number' => 'ABC8', 'items' => 12, 'active' => 0],
            3 => ['id' => 3, 'items' => 13, 'active' => 1],
            4 => ['id' => 4, 'number' => 'ABC6', 'items' => 14, 'active' => 0],
            5 => ['id' => 5, 'number' => 'ABC5', 'items' => 15, 'active' => 0],
            6 => ['id' => 6, 'number' => 'ABC4', 'items' => 16, 'active' => 1],
            7 => ['id' => 7, 'number' => 'ABC3', 'items' => 17, 'active' => 0],
            8 => ['id' => 8, 'number' => 'ABC2', 'items' => 18, 'active' => 1],
            9 => ['id' => 9, 'items' => 19, 'active' => 1],
            10 => ['id' => 10, 'items' => 0, 'active' => 1],
            11 => ['id' => 11, 'items' => null, 'active' => 1],
        ]]);
        $m = new Model($p, 'invoices');
        $m->addField('items', ['type' => 'integer']);

        $this->assertSame(13.5, $m->action('fx', ['avg', 'items'])->getOne());
        $this->assertSame(12.272727272727273, $m->action('fx0', ['avg', 'items'])->getOne());
        $this->assertSame(0, $m->action('fx', ['min', 'items'])->getOne());
        $this->assertSame(19, $m->action('fx', ['max', 'items'])->getOne());
        $this->assertSame(135, $m->action('fx', ['sum', 'items'])->getOne());
    }

    public function testExists()
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['id' => 1, 'number' => 'ABC9', 'items' => 11, 'active' => 1],
        ]]);
        $m = new Model($p, 'invoices');
        $m->addField('items', ['type' => 'integer']);

        $this->assertSame(1, $m->action('exists')->getOne());

        $m->delete(1);

        $this->assertSame(0, $m->action('exists')->getOne());
    }

    /**
     * Returns exported data, but will use get() instead of export().
     */
    protected function _getRows(Model $model, array $fields = []): array
    {
        $d = [];
        foreach ($model as $row) {
            $rowData = $row->get();
            $rowData = $fields ? array_intersect_key($rowData, array_flip($fields)) : $rowData;
            $d[] = $rowData;
        }

        return $d;
    }

    /**
     * Test Model->setOrder().
     */
    public function testOrder()
    {
        $dbData = [
            1 => ['id' => 1, 'f1' => 'A', 'f2' => 'B'],
            2 => ['id' => 2, 'f1' => 'D', 'f2' => 'A'],
            3 => ['id' => 3, 'f1' => 'D', 'f2' => 'C'],
            4 => ['id' => 4, 'f1' => 'A', 'f2' => 'C'],
            5 => ['id' => 5, 'f1' => 'E', 'f2' => 'A'],
            6 => ['id' => 6, 'f1' => 'C', 'f2' => 'A'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1');
        $d = $this->_getRows($m, ['f1']);
        $this->assertSame([
            ['f1' => 'A'],
            ['f1' => 'A'],
            ['f1' => 'C'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by one field descending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1', 'desc');
        $d = $this->_getRows($m, ['f1']);
        $this->assertSame([
            ['f1' => 'E'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'C'],
            ['f1' => 'A'],
            ['f1' => 'A'],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by two fields ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');

        $m->setOrder('f1', 'desc');
        $m->setOrder('f2', 'desc');
        $d = $this->_getRows($m, ['f1', 'f2', 'id']);
        $this->assertEquals([
            ['f1' => 'E', 'f2' => 'A', 'id' => 5],
            ['f1' => 'D', 'f2' => 'C', 'id' => 3],
            ['f1' => 'D', 'f2' => 'A', 'id' => 2],
            ['f1' => 'C', 'f2' => 'A', 'id' => 6],
            ['f1' => 'A', 'f2' => 'C', 'id' => 4],
            ['f1' => 'A', 'f2' => 'B', 'id' => 1],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1', 'f2', 'id']))); // array_values to get rid of keys
    }

    public function testNoKeyException()
    {
        $p = new Persistence\Array_([
            ['id' => 3, 'f1' => 'A'],
            ['id' => 5, 'f1' => 'D'],
        ]);
        $m = new Model($p);
        $m->addField('f1');

        // array keys do not match id field value
        $this->expectException(Exception::class);
        $m->export();
    }

    public function testImportAndAutoincrement()
    {
        $p = new Persistence\Array_([]);
        $m = new Model($p);
        $m->addField('f1');

        $m->import([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
        ]);
        $this->assertSame(2, $m->action('count')->getOne());

        $m->import([
            ['f1' => 'C'],
            ['f1' => 'D'],
        ]);
        $this->assertSame(4, $m->action('count')->getOne());

        $m->import([
            ['id' => 6, 'f1' => 'E'],
            ['id' => 7, 'f1' => 'F'],
        ]);
        $this->assertSame(6, $m->action('count')->getOne());

        $m->import([
            ['f1' => 'G'],
            ['f1' => 'H'],
        ]);
        $this->assertSame(8, $m->action('count')->getOne());

        $m->import([
            ['id' => 99, 'f1' => 'I'],
            ['id' => 20, 'f1' => 'J'],
        ]);
        $this->assertSame(10, $m->action('count')->getOne());

        $m->import([
            ['f1' => 'K'],
        ]);
        $this->assertSame(11, $m->action('count')->getOne());

        $this->assertSame([
            1 => ['id' => 1, 'f1' => 'A'],
            2 => ['id' => 2, 'f1' => 'B'],
            3 => ['id' => 3, 'f1' => 'C'],
            4 => ['id' => 4, 'f1' => 'D'],
            6 => ['id' => 6, 'f1' => 'E'],
            7 => ['id' => 7, 'f1' => 'F'],
            8 => ['id' => 8, 'f1' => 'G'],
            9 => ['id' => 9, 'f1' => 'H'],
            99 => ['id' => 99, 'f1' => 'I'],
            20 => ['id' => 20, 'f1' => 'J'],
            100 => ['id' => 100, 'f1' => 'K'],
        ], $m->export());
    }

    /**
     * Test Model->setLimit().
     */
    public function testLimit()
    {
        // order by one field ascending
        $p = new Persistence\Array_([
            ['f1' => 'A'],
            ['f1' => 'D'],
            ['f1' => 'E'],
            ['f1' => 'C'],
        ]);
        $m = new Model($p);
        $m->addField('f1');

        $this->assertSame(4, $m->action('count')->getOne());

        $m->setLimit(3);
        $this->assertSame(3, $m->action('count')->getOne());
        $this->assertSame([
            ['id' => 0, 'f1' => 'A'],
            ['id' => 1, 'f1' => 'D'],
            ['id' => 2, 'f1' => 'E'],
        ], array_values($m->export()));

        $m->setLimit(2, 1);
        $this->assertSame(2, $m->action('count')->getOne());
        $this->assertSame([
            ['id' => 1, 'f1' => 'D'],
            ['id' => 2, 'f1' => 'E'],
        ], array_values($m->export()));

        // well, this is strange, that you can actually change limit on-the-fly and then previous
        // limit is not taken into account, but most likely you will never set it multiple times
        $m->setLimit(3);
        $this->assertSame(3, $m->action('count')->getOne());
    }

    /**
     * Test Model->addCondition().
     */
    public function testCondition()
    {
        $p = new Persistence\Array_($dbData = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            3 => ['name' => 'Sarah', 'surname' => 'XX'],
            4 => ['name' => 'Sarah', 'surname' => 'Smith'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(4, $m->action('count')->getOne());
        $this->assertSame(['data' => $dbData], $this->getInternalPersistenceData($p));

        $m->addCondition('name', 'Sarah');
        $this->assertSame(3, $m->action('count')->getOne());

        $m->addCondition('surname', 'Smith');
        $this->assertSame(1, $m->action('count')->getOne());
        $this->assertSame([4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith']], $m->export());
        $this->assertSame([4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith']], $m->action('select')->get());

        $m->addCondition('surname', 'Siiiith');
        $this->assertSame(0, $m->action('count')->getOne());
    }

    public function testUnsupportedAction()
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Exception::class);
        $m->action('foo');
    }

    public function testUnsupportedAggregate()
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->action('fx', ['UNSUPPORTED', 'name']);
    }

    public function testUnsupportedCondition1()
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Exception::class);
        $m->addCondition('name');
    }

    public function testUnsupportedCondition2()
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Exception::class);
        $m->addCondition(new Model(), 'like', '%o%');
    }

    /**
     * Test Model->hasOne().
     */
    public function testHasOne()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ]);

        $user = new Model($p, 'user');
        $user->addField('name');
        $user->addField('surname');

        $country = new Model();
        $country->table = 'country';
        $country->addField('name');

        $user->hasOne('country_id', $country);

        $uu = (clone $user)->load(1);
        $this->assertSame('Latvia', $uu->ref('country_id')->get('name'));

        $uu = (clone $user)->load(2);
        $this->assertSame('UK', $uu->ref('country_id')->get('name'));
    }

    /**
     * Test Model->hasMany().
     */
    public function testHasMany()
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
                3 => ['name' => 'Janis', 'surname' => 'Berzins', 'country_id' => 1],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ]);

        $country = new Model($p, 'country');
        $country->addField('name');

        $user = new Model();
        $user->table = 'user';
        $user->addField('name');
        $user->addField('surname');

        $country->hasMany('Users', $user);
        $user->hasOne('country_id', $country);

        $cc = (clone $country)->load(1);
        $this->assertSame(2, $cc->ref('Users')->action('count')->getOne());

        $cc = (clone $country)->load(2);
        $this->assertSame(1, $cc->ref('Users')->action('count')->getOne());
    }

    public function testLoadAnyThrowsExceptionOnRecordNotFound()
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $this->expectExceptionCode(404);
        $m->loadAny();
    }

    public function testTryLoadAnyNotThrowsExceptionOnRecordNotFound()
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->tryLoadAny();
        $this->assertFalse($m->loaded());
    }

    public function testTryLoadAnyReturnsFirstRecord()
    {
        $a = [
            2 => ['name' => 'John', 'surname' => 'Smith'],
            3 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->tryLoadAny();
        $this->assertSame(2, $m->getId());
    }
}
