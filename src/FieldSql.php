<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Dsql\Expression;
use Atk4\Dsql\Expressionable;

/**
 * Class description?
 *
 * @property Persistence\Sql\Join $join
 */
class FieldSql extends Field implements Expressionable
{
    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expression ||
            $value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }
}
