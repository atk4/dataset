<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Person as Person;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class CsvTest extends AtkPhpunit\TestCase
{
    protected $file;
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        // better to skip this test on Windows, prevent permissions issues
        // see also https://github.com/atk4/data/issues/271
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Skip on Windows');
        }

        $this->file = sys_get_temp_dir() . '/atk4_test__data__a.csv';
        $this->file2 = sys_get_temp_dir() . '/atk4_test__data__b.csv';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->file)) {
            unlink($this->file);
        }
        if (file_exists($this->file2)) {
            unlink($this->file2);
        }
    }

    protected function setDb($data): void
    {
        $f = fopen($this->file, 'w');
        fputcsv($f, array_keys(reset($data)));
        foreach ($data as $row) {
            fputcsv($f, $row);
        }
        fclose($f);
    }

    protected function getDb(): array
    {
        $f = fopen($this->file, 'r');
        $keys = fgetcsv($f);
        $data = [];
        while ($row = fgetcsv($f)) {
            $data[] = array_combine($keys, $row);
        }
        fclose($f);

        return $data;
    }

    /**
     * Test constructor.
     */
    public function testTestcase()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);
        $data2 = $this->getDb();
        $this->assertSame($data, $data2);
    }

    public function testLoadAny()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();

        $this->assertSame('John', $m->get('name'));
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testLoadAnyException()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();
        $m->loadAny();

        $this->assertSame('Sarah', $m->get('name'));
        $this->assertSame('Jones', $m->get('surname'));

        $m->tryLoadAny();
        $this->assertFalse($m->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $p2 = new Persistence\Csv($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            $m2->save($row->get());
        }

        $this->assertSame(
            file_get_contents($this->file2),
            file_get_contents($this->file)
        );
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
