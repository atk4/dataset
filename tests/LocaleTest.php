<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Core\Translator\Translator;
use Atk4\Data\Exception;
use Atk4\Data\Locale;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class LocaleTest extends AtkPhpunit\TestCase
{
    public function testException()
    {
        $this->expectException(Exception::class);
        $exc = new Locale();
    }

    public function testGetPath()
    {
        $rootDir = realpath(dirname(__DIR__) . '/src/..');
        $this->assertSame($rootDir . \DIRECTORY_SEPARATOR . 'locale', realpath(Locale::getPath()));
    }

    public function testLocaleIntegration()
    {
        $trans = Translator::instance();
        $trans->setDefaultLocale('ru');

        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        try {
            $m = new Model($p, 'user');
            $m->addField('name');
            $m->addField('surname');
            $m->load(4);
        } catch (Exception $e) {
            $this->assertStringContainsString('Запись', json_decode($e->getJson(), true)['message']);

            return;
        }

        $this->fail('Expected exception');
    }
}
