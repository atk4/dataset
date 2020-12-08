<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

/**
 * Provides native Model methods for join functionality.
 */
trait JoinsTrait
{
    /**
     * The class used by join() method.
     *
     * @var string|array
     */
    public $_default_seed_join = [Join::class];

    /**
     * Creates an objects that describes relationship between multiple tables (or collections).
     *
     * When object is loaded, then instead of pulling all the data from a single table,
     * join will also query $foreignTable in order to find additional fields. When inserting
     * the record will be also added inside $foreignTable and relationship will be maintained.
     *
     * @param array $defaults
     */
    public function join(string $foreignTable, $defaults = []): Join
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        } elseif (isset($defaults[0])) {
            $defaults['master_field'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $foreignTable;

        return $this->add(Join::fromSeed($this->_default_seed_join, $defaults));
    }

    /**
     * Left Join support.
     *
     * @see join()
     *
     * @param array $defaults
     */
    public function leftJoin(string $foreignTable, $defaults = []): Join
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        }
        $defaults['weak'] = true;

        return $this->join($foreignTable, $defaults);
    }
}
