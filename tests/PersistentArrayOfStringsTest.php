<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class PersistentArrayOfStringsTest extends AtkPhpunit\TestCase
{
    /**
     * Test typecasting.
     */
    public function testTypecasting()
    {
        $p = new Persistence\ArrayOfStrings([
            'user' => [],
        ]);
        $m = new Model($p, 'user');
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('boolean_enum', ['type' => 'boolean', 'enum' => ['N', 'Y']]);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);

        $m->setMulti([
            'string' => "Two\r\nLines  ",
            'text' => "Two\r\nLines  ",
            'integer' => 123,
            'money' => 123.45,
            'float' => 123.456789,
            'boolean' => true,
            'boolean_enum' => 'N',
            'date' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'datetime' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'time' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'array' => ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']],
            'object' => (object) ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']],
        ]);
        $m->saveAndUnload();

        // no typecasting option set in export()
        $data = $m->export(null, null, false);
        $this->assertSame([1 => [
            'id' => 1,
            'string' => 'TwoLines',
            'text' => "Two\nLines",
            'integer' => '123',
            'money' => '123.45',
            'float' => '123.456789',
            'boolean' => '1',
            'boolean_enum' => '0',
            'date' => '2019-01-20',
            'datetime' => '2019-01-20T12:23:34+00:00',
            'time' => '12:23:34',
            'array' => '{"foo":"bar","int":123,"rows":["a","b"]}',
            'object' => '{"foo":"bar","int":123,"rows":["a","b"]}',
        ]], $data);

        // typecasting enabled in export()
        $data = $m->export(null, null, true);
        $this->assertInstanceOf('DateTime', $data[1]['date']);
        $this->assertInstanceOf('DateTime', $data[1]['datetime']);
        $this->assertInstanceOf('DateTime', $data[1]['time']);
        $this->assertTrue(is_array($data[1]['array']));
        $this->assertTrue(is_object($data[1]['object']));
    }
}
