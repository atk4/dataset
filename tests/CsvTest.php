<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Person;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class CsvTest extends AtkPhpunit\TestCase
{
    protected $file;
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = fopen('php://memory', 'w+');
        $this->file2 = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        fclose($this->file);
        fclose($this->file2);
    }

    protected function makeCsvPersistence($fileHandle, array $defaults = []): Persistence\Csv
    {
        return new class($fileHandle, $defaults) extends Persistence\Csv {
            private $handleUnloaded;

            public function __construct($fileHandle, $defaults)
            {
                parent::__construct('', $defaults);
                $this->handleUnloaded = $fileHandle;
            }

            public function openFile(string $mode = 'r'): void
            {
                $this->handle = $this->handleUnloaded;
                fseek($this->handle, 0);
            }

            public function closeFile(): void
            {
                if ($this->handle && get_resource_type($this->handle) === 'stream') {
                    $this->handle = null;
                    $this->header = null;
                }
            }
        };
    }

    protected function setDb($data): void
    {
        ftruncate($this->file, 0);
        fputcsv($this->file, array_keys(reset($data)));
        foreach ($data as $row) {
            fputcsv($this->file, $row);
        }

        ftruncate($this->file2, 0);
    }

    protected function getDb(): array
    {
        fseek($this->file, 0);
        $keys = fgetcsv($this->file);
        $data = [];
        while ($row = fgetcsv($this->file)) {
            $data[] = array_combine($keys, $row);
        }

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

        $p = $this->makeCsvPersistence($this->file);
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

        $p = $this->makeCsvPersistence($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = clone $m;
        $mm->loadAny();
        $mm = clone $m;
        $mm->loadAny();

        $this->assertSame('Sarah', $mm->get('name'));
        $this->assertSame('Jones', $mm->get('surname'));

        $mm = clone $m;
        $mm->tryLoadAny();
        $this->assertFalse($mm->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $p2 = $this->makeCsvPersistence($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);
        $m2->reload_after_save = false; // TODO should be not needed after https://github.com/atk4/data/pull/690 is merged

        foreach ($m as $row) {
            (clone $m2)->save($row->get());
        }

        fseek($this->file, 0);
        fseek($this->file2, 0);
        $this->assertSame(
            stream_get_contents($this->file),
            stream_get_contents($this->file2)
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

        $p = $this->makeCsvPersistence($this->file);
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
