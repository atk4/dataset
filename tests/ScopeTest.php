<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Model\Scope;
use Atk4\Data\Model\Scope\Condition;
use Atk4\Dsql\Expression;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class SCountry extends Model
{
    public $table = 'country';

    public $caption = 'Country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type' => 'boolean', 'default' => false]);

        $this->hasMany('Users', new SUser())
            ->addField('user_names', ['field' => 'name', 'concat' => ',']);
    }
}

class SUser extends Model
{
    public $table = 'user';

    public $caption = 'User';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('country_id', new SCountry())
            ->withTitle()
            ->addFields(['country_code' => 'code', 'is_eu']);

        $this->hasMany('Tickets', [new STicket(), 'their_field' => 'user']);
    }
}

class STicket extends Model
{
    public $table = 'ticket';

    public $caption = 'Ticket';

    protected function init(): void
    {
        parent::init();

        $this->addField('number');
        $this->addField('venue');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('user', new SUser());
    }
}

class ScopeTest extends \Atk4\Schema\PhpunitTestCase
{
    protected $user;
    protected $country;
    protected $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = new SCountry($this->db);

        $this->getMigrator($this->country)->dropIfExists()->create();

        // Specifying hasMany here will perform input
        $this->country->import([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Latvia', 'code' => 'LV'],
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Lithuania', 'code' => 'LT', 'is_eu' => true],
            ['name' => 'Russia', 'code' => 'RU'],
            ['name' => 'France', 'code' => 'FR'],
            ['name' => 'Brazil', 'code' => 'BR'],
        ]);

        $this->user = new SUser($this->db);

        $this->getMigrator($this->user)->dropIfExists()->create();

        $this->user->import([
            ['name' => 'John', 'surname' => 'Smith', 'country_code' => 'CA'],
            ['name' => 'Jane', 'surname' => 'Doe', 'country_code' => 'LV'],
            ['name' => 'Alain', 'surname' => 'Prost', 'country_code' => 'FR'],
            ['name' => 'Aerton', 'surname' => 'Senna', 'country_code' => 'BR'],
            ['name' => 'Rubens', 'surname' => 'Barichello', 'country_code' => 'BR'],
        ]);

        $this->ticket = new STicket($this->db);

        $this->getMigrator($this->ticket)->dropIfExists()->create();

        $this->ticket->import([
            ['number' => '001', 'venue' => 'Best Stadium', 'user' => 1],
            ['number' => '002', 'venue' => 'Best Stadium', 'user' => 2],
            ['number' => '003', 'venue' => 'Best Stadium', 'user' => 2],
            ['number' => '004', 'venue' => 'Best Stadium', 'user' => 4],
            ['number' => '005', 'venue' => 'Best Stadium', 'user' => 5],
        ]);
    }

    public function testCondition()
    {
        $user = clone $this->user;

        $condition = new Condition('name', 'John');

        $user->scope()->add($condition);

        $user->loadAny();

        $this->assertEquals('Smith', $user->get('surname'));
    }

    public function testContitionToWords()
    {
        $user = clone $this->user;

        $condition = new Condition(new Expression('false'));

        $this->assertEquals('expression \'false\'', $condition->toWords($user));

        $condition = new Condition('country_id/code', 'US');

        $this->assertEquals('User that has reference Country Id where Code is equal to \'US\'', $condition->toWords($user));

        $condition = new Condition('country_id', 2);

        $this->assertEquals('Country Id is equal to \'Latvia\'', $condition->toWords($user));

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $condition = new Condition('name', $user->expr('[surname]'));

            $this->assertEquals('Name is equal to expression \'"user"."surname"\'', $condition->toWords($user));
        }

        $condition = new Condition('country_id', null);

        $this->assertEquals('Country Id is equal to empty', $condition->toWords($user));

        $condition = new Condition('name', '>', 'Test');

        $this->assertEquals('Name is greater than \'Test\'', $condition->toWords($user));

        $condition = (new Condition('country_id', 2))->negate();

        $this->assertEquals('Country Id is not equal to \'Latvia\'', $condition->toWords($user));

        $condition = new Condition($user->getField('surname'), $user->getField('name'));

        $this->assertEquals('Surname is equal to User Name', $condition->toWords($user));

        $country = clone $this->country;

        $country->addCondition('Users/#', '>', 0);

        $this->assertEquals('Country that has reference Users where number of records is greater than \'0\'', $country->scope()->toWords());
    }

    public function testContitionUnsupportedToWords()
    {
        $condition = new Condition('name', 'abc');

        $this->expectException(Exception::class);
        $condition->toWords();
    }

    public function testContitionUnsupportedOperator()
    {
        $country = clone $this->country;

        $this->expectException(Exception::class);
        $country->addCondition('name', '==', 'abc');
    }

    public function testContitionUnsupportedNegate()
    {
        $condition = new Condition(new Expression('false'));

        $this->expectException(Exception::class);
        $condition->negate();
    }

    public function testRootScopeUnsupportedNegate()
    {
        $country = clone $this->country;

        $this->expectException(Exception::class);
        $country->scope()->negate();
    }

    public function testConditionOnReferencedRecords()
    {
        $user = clone $this->user;

        $user->addCondition('country_id/code', 'LV');

        $this->assertEquals(1, $user->action('count')->getOne());

        foreach ($user as $u) {
            $this->assertEquals('LV', $u->get('country_code'));
        }

        $user = clone $this->user;

        // users that have no ticket
        $user->addCondition('Tickets/#', 0);

        $this->assertEquals(1, $user->action('count')->getOne());

        foreach ($user as $u) {
            $this->assertTrue(in_array($u->get('name'), ['Alain', 'Aerton', 'Rubens'], true));
        }

        $country = clone $this->country;

        // countries with more than one user
        $country->addCondition('Users/#', '>', 1);

        foreach ($country as $c) {
            $this->assertEquals('BR', $c->get('code'));
        }

        $country = clone $this->country;

        // countries with users that have ticket number 001
        $country->addCondition('Users/Tickets/number', '001');

        foreach ($country as $c) {
            $this->assertEquals('CA', $c->get('code'));
        }

        $country = clone $this->country;

        // countries with users that have more than one ticket
        $country->addCondition('Users/Tickets/#', '>', 1);

        foreach ($country as $c) {
            $this->assertEquals('LV', $c->get('code'));
        }

        $country = clone $this->country;

        // countries with users that have any tickets
        $country->addCondition('Users/Tickets/#', '>', 0);

        $this->assertEquals(3, $country->action('count')->getOne());

        foreach ($country as $c) {
            $this->assertTrue(in_array($c->get('code'), ['LV', 'CA', 'BR'], true));
        }

        $country = clone $this->country;

        // countries with users that have no tickets
        $country->addCondition('Users/Tickets/#', 0);

        $this->assertEquals(1, $country->action('count')->getOne());

        foreach ($country as $c) {
            $this->assertTrue(in_array($c->get('code'), ['FR'], true));
        }

        $user = clone $this->user;

        // users with tickets that have more than two users per country
        // test if a model can be referenced multiple times
        // and if generated query has no duplicate column names
        // because of counting/# field if added multiple times
        $user->addCondition('Tickets/user/country_id/Users/#', '>', 1);
        $user->addCondition('Tickets/user/country_id/Users/#', '>', 1);
        $user->addCondition('Tickets/user/country_id/Users/#', '>=', 2);
        $user->addCondition('Tickets/user/country_id/Users/country_id/Users/#', '>', 1);
        if (!$this->getDatabasePlatform() instanceof SqlitePlatform) {
            // not supported because of limitation/issue in Sqlite, the generated query fails
            // with error: "parser stack overflow"
            $user->addCondition('Tickets/user/country_id/Users/country_id/Users/name', '!=', null); // should be always true
        }

        $this->assertEquals(2, $user->action('count')->getOne());
        foreach ($user as $u) {
            $this->assertTrue(in_array($u->get('name'), ['Aerton', 'Rubens'], true));
        }
    }

    public function testScope()
    {
        $user = clone $this->user;

        $condition1 = ['name', 'John'];
        $condition2 = new Condition('country_code', 'CA');

        $condition3 = ['surname', 'Doe'];
        $condition4 = new Condition('country_code', 'LV');

        $scope1 = Scope::createAnd($condition1, $condition2);
        $scope2 = Scope::createAnd($condition3, $condition4);

        $scope = Scope::createOr($scope1, $scope2);

        $this->assertEquals(Scope::OR, $scope->getJunction());

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')', $scope->toWords($user));

        $user->scope()->add($scope);

        $this->assertSame($user, $scope->getModel());

        $this->assertEquals(2, count($user->export()));

        $this->assertEquals($scope->toWords($user), $user->scope()->toWords());

        // TODO once PHP7.3 support is dropped, we should use WeakRef for owner
        // and unset($scope); here
        // now we need a clone
        // we should fix then also the short_name issue (if it was generated on adding
        // to an owner but owner is removed, the short_name should be removed as well)
        $scope1 = clone $scope1;
        $scope2 = clone $scope2;
        $scope = Scope::createOr($scope1, $scope2);

        $scope->addCondition('country_code', 'BR');

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\') or Code is equal to \'BR\'', $scope->toWords($user));

        $user = clone $this->user;

        $user->scope()->add($scope);

        $this->assertEquals(4, count($user->export()));
    }

    public function testScopeToWords()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'CA');

        $scope1 = Scope::createAnd($condition1, $condition2);
        $condition3 = (new Condition('surname', 'Prost'))->negate();

        $scope = Scope::createAnd($scope1, $condition3);

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->toWords($user));
    }

    public function testNegate()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', '!=', 'Alain');
        $condition2 = new Condition('country_code', '!=', 'FR');

        $condition = Scope::createOr($condition1, $condition2)->negate();

        $user->scope()->add($condition);

        foreach ($user as $u) {
            $this->assertTrue($u->get('name') === 'Alain' && $u->get('country_code') === 'FR');
        }
    }

    public function testAnd()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);

        $scope = Scope::createOr($scope, new Condition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'FR\') or Name is equal to \'John\'', $scope->toWords($user));
    }

    public function testOr()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createOr($condition1, $condition2);

        $scope = Scope::createAnd($scope, new Condition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' or Code is equal to \'FR\') and Name is equal to \'John\'', $scope->toWords($user));
    }

    public function testMerge()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);

        $this->assertEquals('Name is equal to \'Alain\' and Code is equal to \'FR\'', $scope->toWords($user));
    }

    public function testDestroyEmpty()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);

        $scope->clear();

        $this->assertTrue($scope->isEmpty());

        $this->assertEmpty($scope->toWords($user));
    }

    public function testInvalid1()
    {
        $this->expectException(Exception::class);
        new Condition('name', '>', ['a', 'b']);
    }

    public function testInvalid2()
    {
        $this->expectException(Exception::class);
        new Condition('name', ['a', 'b' => ['c']]);
    }
}
