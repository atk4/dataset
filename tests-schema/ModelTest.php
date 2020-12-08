<?php

declare(strict_types=1);

namespace Atk4\Schema\Tests;

use Atk4\Data\Model;
use Atk4\Schema\PhpunitTestCase;

class ModelTest extends PhpunitTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testSetModelCreate()
    {
        $this->dropTableIfExists('user');
        $user = new TestUser($this->db);

        $this->getMigrator($user)->create();

        // now we can use user
        $user->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
    }

    public function testImportTable()
    {
        $this->assertTrue(true);

        return; // TODO enable once import to Model is supported using DBAL
        $this->dropTableIfExists('user');

        $migrator = $this->getMigrator();

        $migrator->table('user')->id()
            ->field('foo')
            ->field('str', ['type' => 'string'])
            ->field('bool', ['type' => 'boolean'])
            ->field('int', ['type' => 'integer'])
            ->field('mon', ['type' => 'money'])
            ->field('flt', ['type' => 'float'])
            ->field('date', ['type' => 'date'])
            ->field('datetime', ['type' => 'datetime'])
            ->field('time', ['type' => 'time'])
            ->field('txt', ['type' => 'text'])
            ->field('arr', ['type' => 'array'])
            ->field('obj', ['type' => 'object'])
            ->create();

        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'quite short value, max 255 characters',
                'str' => 'quite short value, max 255 characters',
                'bool' => true,
                'int' => 123,
                'mon' => 123.45,
                'flt' => 123.456789,
                'date' => (new \DateTime())->format('Y-m-d'),
                'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                'time' => (new \DateTime())->format('H:i:s'),
                'txt' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'arr' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'obj' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
            ])->insert();

        $migrator2 = $this->getMigrator();
        $migrator2->importTable('user');

        $migrator2->mode('create');

        $q1 = preg_replace('/\([0-9,]*\)/i', '', $migrator->render()); // remove parenthesis otherwise we can't differ money from float etc.
        $q2 = preg_replace('/\([0-9,]*\)/i', '', $migrator2->render());
        $this->assertSame($q1, $q2);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMigrateTable()
    {
        $this->dropTableIfExists('user');
        $migrator = $this->getMigrator();
        $migrator->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->insert();
    }

    public function testCreateModel()
    {
        $this->assertTrue(true);

        return; // TODO enable once create from Model is supported using DBAL
        $this->dropTableIfExists('user');

        $this->getMigrator(new TestUser($this->db))->create();

        $user_model = $this->getMigrator()->createModel($this->db, 'user');

        $this->assertSame(
            [
                'name',
                'password',
                'is_admin',
                'notes',
                'main_role_id', // our_field here not role_id (reference name)
            ],
            array_keys($user_model->getFields())
        );
    }

    public function testStringFieldCaseInsensitive()
    {
        $this->dropTableIfExists('user');

        $this->getMigrator()->table('user')->id()
            ->field('string')
            ->field('text', ['type' => 'text'])
            ->field('blob', ['type' => 'blob'])
            ->create();

        $model = new Model($this->db, 'user');
        $model->addField('string');
        $model->addField('text');
        $model->addField('blob');
        $model->setOrder('id');
        $model->import([
            ['id' => 1, 'string' => 'MixedCase'],
            ['id' => 2, 'text' => 'MixedCase'],
            ['id' => 3, 'blob' => 'MixedCase'],
        ]);

        $model->addCondition(Model\Scope::createOr(
            ['string', 'MIXEDcase'],
            ['text', 'MIXEDcase'],
            ['blob', 'MIXEDcase'],
        ));

        $this->assertSame([['id' => '1'], ['id' => '2']], $model->export(['id']));
    }
}

class TestUser extends \Atk4\Data\Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type' => 'password']);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role_id', [TestRole::class, 'our_field' => 'main_role_id', 'their_field' => 'id']);
    }
}

class TestRole extends \Atk4\Data\Model
{
    public $table = 'role';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasMany('Users', [TestUser::class, 'our_field' => 'id', 'their_field' => 'main_role_id']);
    }
}
