<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class JoinSqlTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testDirection()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');

        $j = $m->join('contact');
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('contact_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact2.test_id');
        $this->assertTrue($this->getProtected($j, 'reverse'));
        $this->assertSame('id', $this->getProtected($j, 'master_field'));
        $this->assertSame('test_id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact3', 'test_id');
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact3', ['test_id']);
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact4.foo_id', ['test_id', 'reverse' => true]);
        $this->assertTrue($this->getProtected($j, 'reverse'));
        $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('foo_id', $this->getProtected($j, 'foreign_field'));
    }

    public function testDirectionException()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');

        $this->expectException(Exception::class);
        $j = $m->join('contact.foo_id', 'test_id');
    }

    public function testJoinSaving1()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->getDatabasePlatform() instanceof SQLServer2012Platform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->markTestIncomplete('TODO - NULL PK not unset in INSERT');
        }

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ]);

        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = clone $m_u;
        $m_u2->set('name', 'John');
        $m_u2->set('contact_phone', '+123');

        $m_u2->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));

        $m_u2 = clone $m_u;
        $m_u2->set('name', 'Joe');
        $m_u2->set('contact_phone', '+321');
        $m_u2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinSaving2()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);
        $m_u->addField('name');
        $j = $m_u->join('contact.test_id');
        $j->addFields(['contact_phone']);

        $m_u2 = clone $m_u;
        $m_u2->set('name', 'John');
        $m_u2->set('contact_phone', '+123');
        $m_u2->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));

        $m_u2->unload();
        $m_u2 = clone $m_u;
        $m_u2->set('name', 'Peter');
        $m_u2->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
            ], 'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'test_id' => 2, 'contact_phone' => null],
            ],
        ], $this->getDb(['user', 'contact']));

        $this->db->connection->dsql()->table('contact')->where('id', 2)->delete();

        if ($this->getDatabasePlatform() instanceof OraclePlatform) { // TODO
            $this->markTestIncomplete('TODO - for some reasons, result below has one different key');
        }

        $m_u2->unload();
        $m_u2 = clone $m_u;
        $m_u2->set('name', 'Sue');
        $m_u2->set('contact_phone', '+444');
        $m_u2->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Sue'],
            ], 'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                3 => ['id' => 3, 'test_id' => 3, 'contact_phone' => '+444'],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinSaving3()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->getDatabasePlatform() instanceof SQLServer2012Platform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->markTestIncomplete('TODO - NULL PK not unset in INSERT');
        }

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'test_id' => 0],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ]);

        $m_u->addField('name');
        $j = $m_u->join('contact', 'test_id');
        $j->addField('contact_phone');

        $m_u->set('name', 'John');
        $m_u->set('contact_phone', '+123');

        $m_u->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'test_id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinLoading()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = (clone $m_u)->load(1);
        $this->assertEquals([
            'name' => 'John', 'contact_id' => 1, 'contact_phone' => '+123', 'id' => 1,
        ], $m_u2->get());

        $m_u2 = (clone $m_u)->load(3);
        $this->assertEquals([
            'name' => 'Joe', 'contact_id' => 2, 'contact_phone' => '+321', 'id' => 3,
        ], $m_u2->get());

        $m_u2 = (clone $m_u)->tryLoad(4);
        $this->assertEquals([
            'name' => null, 'contact_id' => null, 'contact_phone' => null, 'id' => null,
        ], $m_u2->get());
    }

    public function testJoinUpdate()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->getDatabasePlatform() instanceof SQLServer2012Platform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->markTestIncomplete('TODO - NULL PK not unset in INSERT');
        }

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = (clone $m_u)->load(1);
        $m_u2->set('name', 'John 2');
        $m_u2->set('contact_phone', '+555');
        $m_u2->save();

        $this->assertEquals(
            [
                'user' => [
                    1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                    2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                    3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
                ], 'contact' => [
                    1 => ['id' => 1, 'contact_phone' => '+555'],
                    2 => ['id' => 2, 'contact_phone' => '+321'],
                ],
            ],
            $this->getDb()
        );

        $m_u2 = (clone $m_u)->load(1);
        $m_u2->set('name', 'XX');
        $m_u2->set('contact_phone', '+999');
        $m_u2 = (clone $m_u)->load(3);
        $m_u2->set('name', 'XX');
        $m_u2->save();

        $this->assertEquals(
            [
                'user' => [
                    1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                    2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                    3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                ], 'contact' => [
                    1 => ['id' => 1, 'contact_phone' => '+555'],
                    2 => ['id' => 2, 'contact_phone' => '+321'],
                ],
            ],
            $this->getDb()
        );

        $m_u2->set('contact_phone', '+999');
        $m_u2->save();

        $this->assertEquals(
            [
                'user' => [
                    1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                    2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                    3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                ], 'contact' => [
                    1 => ['id' => 1, 'contact_phone' => '+555'],
                    2 => ['id' => 2, 'contact_phone' => '+999'],
                ],
            ],
            $this->getDb()
        );

        $m_u2 = (clone $m_u)->tryLoad(4);
        $m_u2->set('name', 'YYY');
        $m_u2->set('contact_phone', '+777');
        $m_u2->save();

        $this->assertEquals(
            [
                'user' => [
                    1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                    2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                    3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                    4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
                ], 'contact' => [
                    1 => ['id' => 1, 'contact_phone' => '+555'],
                    2 => ['id' => 2, 'contact_phone' => '+999'],
                    3 => ['id' => 3, 'contact_phone' => '+777'],
                ],
            ],
            $this->getDb()
        );
    }

    public function testJoinDelete()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u->load(1);
        $m_u->delete();

        $this->assertEquals(
            [
                'user' => [
                    2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                    3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                    4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
                ], 'contact' => [
                    2 => ['id' => 2, 'contact_phone' => '+999'],
                    3 => ['id' => 3, 'contact_phone' => '+777'],
                ],
            ],
            $this->getDb()
        );
    }

    public function testDoubleSaveHook()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ], 'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);
        $m_u->addField('name');
        $j = $m_u->join('contact.test_id');
        $j->addField('contact_phone');

        $m_u->onHook(Model::HOOK_AFTER_SAVE, static function ($m) {
            if ($m->get('contact_phone') !== '+123') {
                $m->set('contact_phone', '+123');
                $m->save();
            }
        });

        $m_u->set('name', 'John');
        $m_u->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));
    }

    public function testDoubleJoin()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->getDatabasePlatform() instanceof SQLServer2012Platform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->markTestIncomplete('TODO - NULL PK not unset in INSERT');
        }

        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John 2', 'contact_id' => 100],
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
            ], 'contact' => [
                100 => ['id' => 100, 'contact_phone' => '+555', 'country_id' => 1],
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
            ], 'country' => [
                1 => ['id' => 1, 'name' => 'UK'],
                2 => ['id' => 2, 'name' => 'US'],
                3 => ['id' => 3, 'name' => 'India'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j_contact = $m_u->join('contact');
        $j_contact->addField('contact_phone');
        $j_country = $j_contact->join('country');
        $j_country->addField('country_name', ['actual' => 'name']);

        $m_u2 = (clone $m_u)->load(10);
        $m_u2->delete();

        $m_u2 = (clone $m_u)->loadBy('country_name', 'US');
        $this->assertEquals(30, $m_u2->getId());
        $m_u2->set('country_name', 'USA');
        $m_u2->save();

        $m_u2 = (clone $m_u)->tryLoad(40);
        $this->assertFalse($m_u2->loaded());

        $this->assertSame($m_u2->getField('country_id')->getJoin(), $m_u2->getField('contact_phone')->getJoin());

        (clone $m_u)->save(['name' => 'new', 'contact_phone' => '+000', 'country_name' => 'LV']);

        $this->assertEquals(
            [
                'user' => [
                    20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                    30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                    40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
                    41 => ['id' => 41, 'name' => 'new', 'contact_id' => 301],
                ], 'contact' => [
                    200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                    300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
                    301 => ['id' => 301, 'contact_phone' => '+000', 'country_id' => 4],
                ], 'country' => [
                    2 => ['id' => 2, 'name' => 'USA'],
                    3 => ['id' => 3, 'name' => 'India'],
                    4 => ['id' => 4, 'name' => 'LV'],
                ],
            ],
            $this->getDb()
        );
    }

    public function testDoubleReverseJoin()
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John 2', 'contact_id' => 100],
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
            ], 'contact' => [
                100 => ['id' => 100, 'contact_phone' => '+555', 'country_id' => 1],
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
            ], 'country' => [
                1 => ['id' => 1, 'name' => 'UK'],
                2 => ['id' => 2, 'name' => 'US'],
                3 => ['id' => 3, 'name' => 'India'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');
        $c = $j->join('country');
        $c->addFields([['country_name', ['actual' => 'name']]]);

        $m_u2 = (clone $m_u)->load(10);
        $m_u2->delete();

        $m_u->loadBy('country_name', 'US');
        $this->assertEquals(30, $m_u->getId());

        $this->assertEquals(
            [
                'user' => [
                    20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                    30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                    40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
                ], 'contact' => [
                    200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                    300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
                ], 'country' => [
                    2 => ['id' => 2, 'name' => 'US'],
                    3 => ['id' => 3, 'name' => 'India'],
                ],
            ],
            $this->getDb()
        );
    }

    /**
     * Test hasOne and hasMany trough Join.
     */
    public function testJoinHasOneHasMany()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 10],
                2 => ['id' => 2, 'name' => 'Jane', 'contact_id' => 11],
            ], 'contact' => [ // join User 1:1
                10 => ['id' => 10, 'phone_id' => 20],
                11 => ['id' => 11, 'phone_id' => 21],
            ], 'phone' => [ // each Contact hasOne phone
                20 => ['id' => 20, 'number' => '+123'],
                21 => ['id' => 21, 'number' => '+456'],
            ], 'token' => [ // each User hassMany Token
                30 => ['id' => 30, 'user_id' => 1, 'token' => 'ABC'],
                31 => ['id' => 31, 'user_id' => 1, 'token' => 'DEF'],
                32 => ['id' => 32, 'user_id' => 2, 'token' => 'GHI'],
            ], 'email' => [ // each Contact hasMany Email
                40 => ['id' => 40, 'contact_id' => 10, 'address' => 'john@foo.net'],
                41 => ['id' => 41, 'contact_id' => 10, 'address' => 'johnny@foo.net'],
                42 => ['id' => 42, 'contact_id' => 11, 'address' => 'jane@foo.net'],
            ],
        ]);

        // main user model joined to contact table
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');

        $m_u->load(1);
        $this->assertEquals([
            'id' => 1, 'name' => 'John', 'contact_id' => 10,
        ], $m_u->get());

        // hasOne phone model
        $m_p = new Model($db, 'phone');
        $m_p->addField('number');
        $ref = $j->hasOne('phone_id', $m_p); // hasOne on JOIN
        $ref->addField('number');

        $m_u->load(1);
        $this->assertEquals([
            'id' => 1, 'name' => 'John', 'contact_id' => 10, 'phone_id' => 20, 'number' => '+123',
        ], $m_u->get());

        // hasMany token model (uses default our_field, their_field)
        $m_t = new Model($db, 'token');
        $m_t->addField('user_id');
        $m_t->addField('token');
        $ref = $j->hasMany('Token', $m_t); // hasMany on JOIN (use default our_field, their_field)

        $m_u->load(1);
        $this->assertEquals([
            ['id' => 30, 'user_id' => 1, 'token' => 'ABC'],
            ['id' => 31, 'user_id' => 1, 'token' => 'DEF'],
        ], $m_u->ref('Token')->export());

        // hasMany email model (uses custom our_field, their_field)
        $m_e = new Model($db, 'email');
        $m_e->addField('contact_id');
        $m_e->addField('address');
        $ref = $j->hasMany('Email', [$m_e, 'our_field' => 'contact_id', 'their_field' => 'contact_id']); // hasMany on JOIN (use custom our_field, their_field)

        $m_u->load(1);
        $this->assertEquals([
            ['id' => 40, 'contact_id' => 10, 'address' => 'john@foo.net'],
            ['id' => 41, 'contact_id' => 10, 'address' => 'johnny@foo.net'],
        ], $m_u->ref('Email')->export());
    }
}
