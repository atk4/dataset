<?php

declare(strict_types=1);

namespace Atk4\Data\Action;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * Class Array_ is returned by $model->action(). Compatible with DSQL to a certain point as it implements
 * specific actions such as getOne() or get().
 */
class Iterator
{
    /**
     * @var \ArrayIterator
     */
    public $generator;

    public function __construct(array $data)
    {
        $this->generator = new \ArrayIterator($data);
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @return $this
     */
    public function filter(Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // CallbackFilterIterator with circular reference (bound function) is not GCed,
            // because of specific php implementation of SPL iterator, see:
            // https://bugs.php.net/bug.php?id=80125
            // and related
            // https://bugs.php.net/bug.php?id=65387
            // - PHP 7.3 - impossible to fix easily
            // - PHP 7.4 - fix it using WeakReference
            // - PHP 8.0 - fixed in php, see:
            // https://github.com/php/php-src/commit/afab9eb48c883766b7870f76f2e2b0a4bd575786
            // remove the if below once PHP 7.3 and 7.4 is no longer supported
            $filterFx = function ($row) use ($condition) {
                return $this->match($row, $condition);
            };
            if (PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION === 4) {
                $filterFxWeakRef = \WeakReference::create($filterFx);
                $this->generator = new \CallbackFilterIterator($this->generator, static function (array $row) use ($filterFxWeakRef) {
                    return $filterFxWeakRef->get()($row);
                });
                $this->generator->filterFx = $filterFx; // prevent filter function to be GCed
            } else {
                $this->generator = new \CallbackFilterIterator($this->generator, $filterFx);
            }
        }

        return $this;
    }

    /**
     * Calculates SUM|AVG|MIN|MAX aggragate values for $field.
     *
     * @param string $fx
     * @param string $field
     * @param bool   $coalesce
     *
     * @return \Atk4\Data\Action\Iterator
     */
    public function aggregate($fx, $field, $coalesce = false)
    {
        $result = 0;
        $column = array_column($this->get(), $field);

        switch (strtoupper($fx)) {
            case 'SUM':
                $result = array_sum($column);

            break;
            case 'AVG':
                $column = $coalesce ? $column : array_filter($column, function ($value) {
                    return $value !== null;
                });

                $result = array_sum($column) / count($column);

            break;
            case 'MAX':
                $result = max($column);

            break;
            case 'MIN':
                $result = min($column);

            break;
            default:
                throw (new Exception('Persistence\Array_ driver action unsupported format'))
                    ->addMoreInfo('action', $fx);
        }

        $this->generator = new \ArrayIterator([[$result]]);

        return $this;
    }

    /**
     * Checks if $row matches $condition.
     *
     * @return bool
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition)
    {
        $match = false;

        // simple condition
        if ($condition instanceof Model\Scope\Condition) {
            $args = $condition->toQueryArguments();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if (count($args) === 2) {
                $value = $operator;

                $operator = '=';
            }

            if (!is_a($field, Field::class)) {
                throw (new Exception('Persistence\Array_ driver condition unsupported format'))
                    ->addMoreInfo('reason', 'Unsupported object instance ' . get_class($field))
                    ->addMoreInfo('condition', $condition);
            }

            $match = $this->evaluateIf($row[$field->short_name] ?? null, $operator, $value);
        }

        // nested conditions
        if ($condition instanceof Model\Scope) {
            $matches = [];

            foreach ($condition->getNestedConditions() as $nestedCondition) {
                $matches[] = $subMatch = (bool) $this->match($row, $nestedCondition);

                // do not check all conditions if any match required
                if ($condition->isOr() && $subMatch) {
                    break;
                }
            }

            // any matches && all matches the same (if all required)
            $match = array_filter($matches) && ($condition->isAnd() ? count(array_unique($matches)) === 1 : true);
        }

        return $match;
    }

    protected function evaluateIf($v1, $operator, $v2): bool
    {
        switch (strtoupper((string) $operator)) {
            case '=':
                $result = is_array($v2) ? $this->evaluateIf($v1, 'IN', $v2) : $v1 === $v2;

            break;
            case '>':
                $result = $v1 > $v2;

            break;
            case '>=':
                $result = $v1 >= $v2;

            break;
            case '<':
                $result = $v1 < $v2;

            break;
            case '<=':
                $result = $v1 <= $v2;

            break;
            case '!=':
            case '<>':
                $result = !$this->evaluateIf($v1, '=', $v2);

            break;
            case 'LIKE':
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2));

                $result = (bool) preg_match('/^' . $pattern . '$/', (string) $v1);

            break;
            case 'NOT LIKE':
                $result = !$this->evaluateIf($v1, 'LIKE', $v2);

            break;
            case 'IN':
                $result = is_array($v2) ? in_array($v1, $v2, true) : $this->evaluateIf($v1, '=', $v2);

            break;
            case 'NOT IN':
                $result = !$this->evaluateIf($v1, 'IN', $v2);

            break;
            case 'REGEXP':
                $result = (bool) preg_match('/' . $v2 . '/', $v1);

            break;
            case 'NOT REGEXP':
                $result = !$this->evaluateIf($v1, 'REGEXP', $v2);

            break;
            default:
                throw (new Exception('Unsupported operator'))
                    ->addMoreInfo('operator', $operator);
        }

        return $result;
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function order($fields)
    {
        $data = $this->get();

        // prepare arguments for array_multisort()
        $args = [];
        foreach ($fields as [$field, $direction]) {
            $args[] = array_column($data, $field);
            $args[] = strtolower($direction) === 'desc' ? SORT_DESC : SORT_ASC;
        }
        $args[] = &$data;

        // call sorting
        array_multisort(...$args);

        // put data back in generator
        $this->generator = new \ArrayIterator(array_pop($args));

        return $this;
    }

    /**
     * Limit Iterator.
     *
     * @return $this
     */
    public function limit(int $limit = null, int $offset = 0)
    {
        $data = array_slice($this->get(), $offset, $limit, true);

        // put data back in generator
        $this->generator = new \ArrayIterator($data);

        return $this;
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    public function count()
    {
        $this->generator = new \ArrayIterator([[iterator_count($this->generator)]]);

        return $this;
    }

    /**
     * Checks if iterator has any rows.
     *
     * @return $this
     */
    public function exists()
    {
        $this->generator = new \ArrayIterator([[$this->generator->valid() ? 1 : 0]]);

        return $this;
    }

    /**
     * Return all data inside array.
     */
    public function get(): array
    {
        return iterator_to_array($this->generator, true);
    }

    /**
     * Return one row of data.
     */
    public function getRow(): ?array
    {
        $row = $this->generator->current();
        $this->generator->next();

        return $row;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    public function getOne()
    {
        $data = $this->getRow();

        return reset($data);
    }
}
