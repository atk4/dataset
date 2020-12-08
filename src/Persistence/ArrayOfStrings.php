<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Field;

/**
 * Array persistence which will always typecast all values to strings.
 */
class ArrayOfStrings extends Array_
{
    /**
     * Typecast all values to strings when saving.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $f, $value)
    {
        return $f->toString($value);
    }

    /**
     * Typecast all values from string to correct ones while loading.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $f, $value)
    {
        return $f->normalize($value);
    }
}
