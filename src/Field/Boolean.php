<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\ValidationException;

/**
 * Your favorite nullable binary type.
 */
class Boolean extends \Atk4\Data\Field
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Value which will be used for "true".
     *
     * @var mixed
     */
    public $valueTrue = true;

    /**
     * Value which will be used for "false".
     *
     * @var mixed
     */
    public $valueFalse = false;

    /**
     * Backward compatible way to specify value for true / false:.
     *
     * $enum = ['N', 'Y']
     *
     * @var array
     */
    public $enum;

    /**
     * Constructor.
     */
    protected function init(): void
    {
        $this->_init();

        // Backwards compatibility
        if ($this->enum) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            //unset($this->enum);
        }
    }

    /**
     * Normalize value to boolean value.
     *
     * @param mixed $value
     */
    public function normalize($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        } elseif (is_bool($value)) {
            return $value;
        }

        if ($value === $this->valueTrue) {
            return true;
        } elseif ($value === $this->valueFalse) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        throw new ValidationException([$this->name => 'Must be a boolean value']);
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v === true ? '1' : '0';
    }

    /**
     * Validate if value is allowed for this field.
     *
     * @param mixed $value
     */
    public function validate($value): void
    {
        // if value required, then only valueTrue is allowed
        if ($this->required && $value !== $this->valueTrue) {
            throw new ValidationException([$this->name => 'Must be selected']);
        }
    }
}
