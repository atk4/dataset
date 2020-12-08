<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\ValidationException;

class MyValidationModel extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('domain');
    }

    public function validate($intent = null): array
    {
        $errors = [];
        if ($this->get('name') === 'Python') {
            $errors['name'] = 'Snakes are not allowed on this plane';
        }
        if ($this->get('domain') === 'example.com') {
            $errors['domain'] = 'This domain is reserved for examples only';
        }

        return array_merge(parent::validate(), $errors);
    }
}

class BadValidationModel extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
    }

    public function validate($intent = null): array
    {
        return 'This should be array';
    }
}

class ValidationTests extends AtkPhpunit\TestCase
{
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $p = new Persistence\Array_();
        $this->m = new MyValidationModel($p);
    }

    public function testValidate1()
    {
        $this->m->set('name', 'john');
        $this->m->set('domain', 'gmail.com');
        $this->m->save();
        $this->assertTrue(true); // no exception
    }

    public function testValidate2()
    {
        $this->m->set('name', 'Python');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Snakes');
        $this->m->save();
    }

    public function testValidate3()
    {
        $this->m->set('name', 'Python');
        $this->m->set('domain', 'example.com');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Multiple');
        $this->m->save();
    }

    public function testValidate4()
    {
        try {
            $this->m->set('name', 'Python');
            $this->m->set('domain', 'example.com');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertSame('This domain is reserved for examples only', $e->getParams()['errors']['domain']);

            return;
        }
    }

    public function testValidate5()
    {
        $p = new Persistence\Array_();
        $m = new BadValidationModel($p);

        $this->expectException(\TypeError::class);
        $m->set('name', 'john');
        $m->save();
    }

    public function testValidateHook()
    {
        $this->m->onHook(Model::HOOK_VALIDATE, static function ($m) {
            if ($m->get('name') === 'C#') {
                return ['name' => 'No sharp objects allowed'];
            }
        });

        $this->m->set('name', 'Swift');
        $this->m->save();

        try {
            $this->m->set('name', 'C#');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertSame('No sharp objects allowed', $e->errors['name']);
        }

        try {
            $this->m->set('name', 'Python');
            $this->m->set('domain', 'example.com');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertSame(2, count($e->errors));
        }
    }
}
