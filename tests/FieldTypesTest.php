<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Static_ as Persistence_Static;
use Atk4\Data\ValidationException;

/**
 * Test various Field.
 */
class FieldTypesTest extends \Atk4\Schema\PhpunitTestCase
{
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testEmailBasic()
    {
        $m = new Model($this->pers);
        $m->addField('email', [Field\Email::class]);

        // null value
        $m->set('email', null);
        $m->save();
        $this->assertNull($m->get('email'));

        // normal value
        $m->set('email', 'foo@example.com');
        $m->save();
        $this->assertSame('foo@example.com', $m->get('email'));

        // padding, spacing etc removed
        $m->set('email', " \t " . 'foo@example.com ' . " \n ");
        $m->save();
        $this->assertSame('foo@example.com', $m->get('email'));

        // no domain - go to hell :)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not have domain');
        $m->set('email', 'qq');
    }

    public function testEmailMultipleValues()
    {
        $m = new Model($this->pers);
        $m->addField('email', [Field\Email::class]);
        $m->addField('emails', [Field\Email::class, 'allow_multiple' => true]);

        $m->set('emails', 'bar@exampe.com, foo@example.com');
        $this->assertSame('bar@exampe.com, foo@example.com', $m->get('emails'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('a single email');
        $m->set('email', 'bar@exampe.com, foo@example.com');
    }

    public function testEmailValidateDns()
    {
        $m = new Model($this->pers);
        $m->addField('email', [Field\Email::class, 'dns_check' => true]);

        $m->set('email', ' foo@gmail.com');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('domain does not exist');
        $m->set('email', ' foo@lrcanoetuhasnotdusantotehusontehuasntddaontehudnouhtd.com');
    }

    public function testEmailWithName()
    {
        $m = new Model($this->pers);
        $m->addField('email_name', [Field\Email::class, 'include_names' => true]);
        $m->addField('email_names', [Field\Email::class, 'include_names' => true, 'allow_multiple' => true, 'dns_check' => true, 'separator' => [',', ';']]);
        $m->addField('email_idn', [Field\Email::class, 'dns_check' => true]);
        $m->addField('email', [Field\Email::class]);

        $m->set('email_name', 'Romans <me@gmail.com>');
        $m->set('email_names', 'Romans1 <me1@gmail.com>, Romans2 <me2@gmail.com>; Romans Žlutý Kůň <me3@gmail.com>');
        $m->set('email_idn', 'test@háčkyčárky.cz'); // official IDN test domain

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $m->set('email', 'Romans <me@gmail.com>');
    }
}
