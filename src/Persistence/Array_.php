<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

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
    public function getRawDataByTable(string $table): array
    {
        return $this->data[$table];
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
        if ($table !== null) {
            throw new \Error('debug!!');
        }

        if (!isset($this->data[$model->table])) {
            throw (new Exception('Table was not found in the array data source'))
                ->addMoreInfo('table', $model->table);
        }

        if (!isset($this->data[$model->table][$id])) {
            throw (new Exception('Record with specified ID was not found', 404))
                ->addMoreInfo('id', $id);
        }

        return $this->tryLoad($model, $id);
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param mixed $id
     */
    public function tryLoad(Model $model, $id, string $table = null): ?array
    {
        if ($table !== null) {
            throw new \Error('debug!!');
        }

        if (!isset($this->data[$model->table][$id])) {
            return null;
        }

        return $this->typecastLoadRow($model, $this->data[$model->table][$id]);
    }

    /**
     * Tries to load first available record and return data record.
     * Doesn't throw exception if model can't be loaded or there are no data records.
     */
    public function tryLoadAny(Model $model, string $table = null): ?array
    {
        if ($table !== null) {
            throw new \Error('debug!!');
        }

        if (!$this->data[$model->table]) {
            return null;
        }

        reset($this->data[$model->table]);
        $id = key($this->data[$model->table]);

        $row = $this->load($model, $id);
        $model->id = $id;

        return $row;
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @return mixed
     */
    public function insert(Model $model, array $data, string $table = null)
    {
        if ($table !== null) {
                throw new \Error('debug xxx!! ' . $table . ' vs. ' . $m->table);
        }

        $data = $this->typecastSaveRow($model, $data);

        $id = $this->generateNewId($model);
        if ($model->id_field) {
            $data[$model->id_field] = $id;
        }
        $this->data[$model->table][$id] = $data;

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
        if ($table !== null) {
                throw new \Error('debug xxx!! ' . $table . ' vs. ' . $m->table);
        }

        $data = $this->typecastSaveRow($model, $data);

        $this->data[$model->table][$id] = array_merge($this->data[$model->table][$id] ?? [], $data);

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id, string $table = null)
    {
        if ($table !== null) {
                throw new \Error('debug xxx!! ' . $table . ' vs. ' . $m->table);
        }

        unset($this->data[$model->table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @return string
     */
    public function generateNewId(Model $model)
    {
        if ($model->id_field) {
            $ids = array_keys($this->data[$model->table]);
            $type = $model->getField($model->id_field)->type;
        } else {
            $ids = [count($this->data[$model->table])]; // use ids starting from 1
            $type = 'integer';
        }

        switch ($type) {
            case 'integer':
                $ids = $model->id_field ? array_keys($this->data[$model->table]) : [count($this->data[$model->table])];

                $id = $ids ? max($ids) + 1 : 1;

                break;
            case 'string':
                $id = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        return $this->lastInsertIds[$model->table] = $this->lastInsertIds['$'] = $id;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @param Model $model
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
    public function initAction(Model $model, array $fields = null): \atk4\data\Action\Iterator
    {
        $data = $this->data[$model->table];

        if ($fields !== null) {
            $data = array_map(function ($row) use ($model, $fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $data);
        }

        return new \atk4\data\Action\Iterator($data);
    }

    /**
     * Will set limit defined inside $model onto data.
     */
    protected function setLimitOrder(Model $model, \atk4\data\Action\Iterator $action)
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
     * @return \atk4\data\Action\Iterator|null
     */
    public function applyScope(Model $model, \atk4\data\Action\Iterator $iterator)
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

                return $action->aggregate($fx, $field, $type == 'fx0');
            default:
                throw (new Exception('Unsupported action mode'))
                    ->addMoreInfo('type', $type);
        }
    }
}
