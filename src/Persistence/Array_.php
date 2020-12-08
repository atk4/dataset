<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Array_ extends Persistence
{
    /** @var array */
    private $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Array of last inserted ids per table.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @var array
     */
    protected $lastInsertIds = [];

    /**
     * @deprecated TODO temporary for these:
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsOne.php#L119
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsMany.php#L66
     *             remove once fixed/no longer needed
     */
    public function getRawDataByTable(Model $model, string $table): array
    {
        $rows = [];
        foreach ($this->data[$table] as $id => $row) {
            $this->addIdToLoadRow($model, $row, $id);
            $rows[$id] = $row;
        }

        return $rows;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    private function saveRow(Model $model, array $row, $id, string $table): void
    {
        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
                unset($row[$idColumnName]);
            }
        }

        $this->data[$table][$id] = $row;
    }

    private function addIdToLoadRow(Model $model, array &$row, $id): void
    {
        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
            }

            $row = [$idColumnName => $id] + $row;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(Model $model, array $defaults = []): Model
    {
        if (isset($defaults[0])) {
            $model->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_seed_join' => [Array_\Join::class],
        ], $defaults);

        $model = parent::add($model, $defaults);

        if ($model->id_field && $model->hasField($model->id_field)) {
            $f = $model->getField($model->id_field);
            if (!$f->type) {
                $f->type = 'integer';
            }
        }

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there
        if (!$model->table) {
            $model->table = 'data'; // fake table name 'data'
            if (!isset($this->data[$model->table]) || count($this->data) !== 1) {
                $this->data = [$model->table => $this->data];
            }
        }

        // if there is no such table in persistence, then create empty one
        if (!isset($this->data[$model->table])) {
            $this->data[$model->table] = [];
        }

        return $model;
    }

    /**
     * Loads model and returns data record.
     *
     * @param mixed $id
     */
    public function load(Model $model, $id, string $table = null): array
    {
        if (isset($model->table) && !isset($this->data[$model->table])) {
            throw (new Exception('Table was not found in the array data source'))
                ->addMoreInfo('table', $model->table);
        }

        if (!isset($this->data[$table ?? $model->table][$id])) {
            throw (new Exception('Record with specified ID was not found', 404))
                ->addMoreInfo('id', $id);
        }

        return $this->tryLoad($model, $id, $table);
    }

    /**
     * Tries to load first available record and return data record.
     */
    public function loadAny(Model $model, string $table = null): ?array
    {
        $row = $this->tryLoadAny($model, $table);
        if ($row === null) {
            throw (new Exception('No matching records were found', 404))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        return $row;
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param mixed $id
     */
    public function tryLoad(Model $model, $id, string $table = null): ?array
    {
        $table = $table ?? $model->table;

        if (!isset($this->data[$table][$id])) {
            return null;
        }

        $row = $this->data[$table][$id];
        $this->addIdToLoadRow($model, $row, $id);

        return $this->typecastLoadRow($model, $row);
    }

    /**
     * Tries to load first available record and return data record.
     * Doesn't throw exception if model can't be loaded or there are no data records.
     */
    public function tryLoadAny(Model $model, string $table = null): ?array
    {
        $table = $table ?? $model->table;

        if (!$this->data[$table]) {
            return null;
        }

        reset($this->data[$table]);
        $id = key($this->data[$table]);

        $row = $this->load($model, $id, $table);
        $model->setId($id);

        return $row;
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @return mixed
     */
    public function insert(Model $model, array $data, string $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $id = $data[$model->id_field] ?? $this->generateNewId($model, $table);

        $this->saveRow($model, $data, $id, $table);

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function update(Model $model, $id, array $data, string $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $this->saveRow($model, array_merge($this->data[$table][$id] ?? [], $data), $id, $table);

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id, string $table = null)
    {
        $table = $table ?? $model->table;

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @return string
     */
    public function generateNewId(Model $model, string $table = null)
    {
        $table = $table ?? $model->table;

        $type = $model->id_field ? $model->getField($model->id_field)->type : 'integer';

        switch ($type) {
            case 'integer':
                $ids = $model->id_field ? array_keys($this->data[$table]) : [count($this->data[$table])];

                $id = $ids ? max($ids) + 1 : 1;

                break;
            case 'string':
                $id = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        $this->lastInsertIds[$table] = $id;
        $this->lastInsertIds['$'] = $id;

        return $id;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @return mixed
     */
    public function lastInsertId(Model $model = null)
    {
        if ($model) {
            return $this->lastInsertIds[$model->table] ?? null;
        }

        return $this->lastInsertIds['$'] ?? null;
    }

    /**
     * Prepare iterator.
     */
    public function prepareIterator(Model $model): iterable
    {
        return $model->action('select')->get();
    }

    /**
     * Export all DataSet.
     */
    public function export(Model $model, array $fields = null, bool $typecast = true): array
    {
        $data = $model->action('select', [$fields])->get();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    /**
     * Typecast data and return Iterator of data array.
     */
    public function initAction(Model $model, array $fields = null): \Atk4\Data\Action\Iterator
    {
        $data = $this->data[$model->table];
        array_walk($data, function (&$row, $id) use ($model) {
            $this->addIdToLoadRow($model, $row, $id);
        });

        if ($fields !== null) {
            $data = array_map(function ($row) use ($fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $data);
        }

        return new \Atk4\Data\Action\Iterator($data);
    }

    /**
     * Will set limit defined inside $m onto data.
     */
    protected function setLimitOrder(Model $model, \Atk4\Data\Action\Iterator $action)
    {
        // first order by
        if ($model->order) {
            $action->order($model->order);
        }

        // then set limit
        if ($model->limit && ($model->limit[0] || $model->limit[1])) {
            $action->limit($model->limit[0] ?? 0, $model->limit[1] ?? 0);
        }
    }

    /**
     * Will apply conditions defined inside $model onto $iterator.
     *
     * @return \Atk4\Data\Action\Iterator|null
     */
    public function applyScope(Model $model, \Atk4\Data\Action\Iterator $iterator)
    {
        return $iterator->filter($model->scope());
    }

    /**
     * Various actions possible here, mostly for compatibility with SQLs.
     *
     * @param string $type
     * @param array  $args
     *
     * @return mixed
     */
    public function action(Model $model, $type, $args = [])
    {
        $args = (array) $args;

        switch ($type) {
            case 'select':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action;
            case 'count':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action->count();
            case 'exists':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);

                return $action->exists();
            case 'field':
                if (!isset($args[0])) {
                    throw (new Exception('This action requires one argument with field name'))
                        ->addMoreInfo('action', $type);
                }

                $field = is_string($args[0]) ? $args[0] : $args[0][0];

                $action = $this->initAction($model, [$field]);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                // get first record
                if ($row = $action->getRow()) {
                    if (isset($args['alias']) && array_key_exists($field, $row)) {
                        $row[$args['alias']] = $row[$field];
                        unset($row[$field]);
                    }
                }

                return $row;
            case 'fx':
            case 'fx0':
                if (!isset($args[0], $args[1])) {
                    throw (new Exception('fx action needs 2 arguments, eg: ["sum", "amount"]'))
                        ->addMoreInfo('action', $type);
                }

                [$fx, $field] = $args;

                $action = $this->initAction($model, [$field]);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action->aggregate($fx, $field, $type === 'fx0');
            default:
                throw (new Exception('Unsupported action mode'))
                    ->addMoreInfo('type', $type);
        }
    }
}
