<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\Factory;
use Doctrine\DBAL\Platforms;

class Persistence
{
    use \Atk4\Core\ContainerTrait {
        add as _add;
    }
    use \Atk4\Core\HookTrait;
    use \Atk4\Core\DynamicMethodTrait;
    use \Atk4\Core\NameTrait;
    use \Atk4\Core\DiContainerTrait;

    /** @const string */
    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    /**
     * Connects database.
     *
     * @param string|array $dsn Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                          leaving user and password arguments = null
     */
    public static function connect($dsn, string $user = null, string $password = null, array $args = []): self
    {
        // parse DSN string
        $dsn = \Atk4\Dsql\Connection::normalizeDsn($dsn, $user, $password);

        switch ($dsn['driverSchema']) {
            case 'mysql':
            case 'oci':
            case 'oci12':
                // Omitting UTF8 is always a bad problem, so unless it's specified we will do that
                // to prevent nasty problems. This is un-tested on other databases, so moving it here.
                // It gives problem with sqlite
                if (strpos($dsn['dsn'], ';charset=') === false) {
                    $dsn['dsn'] .= ';charset=utf8mb4';
                }

                // no break
            case 'pgsql':
            case 'sqlsrv':
            case 'sqlite':
                $db = new \Atk4\Data\Persistence\Sql($dsn['dsn'], $dsn['user'], $dsn['pass'], $args);

                return $db;
            default:
                throw (new Exception('Unable to determine persistence driver type from DSN'))
                    ->addMoreInfo('dsn', $dsn['dsn']);
        }
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
    }

    /**
     * Associate model with the data driver.
     */
    public function add(Model $m, array $defaults = []): Model
    {
        $m = Factory::factory($m, $defaults);

        if ($m->persistence) {
            if ($m->persistence === $this) {
                return $m;
            }

            throw new Exception('Model is already related to another persistence');
        }

        $m->persistence = $this;
        $m->persistence_data = [];
        $this->initPersistence($m);
        $m = $this->_add($m);

        $this->hook(self::HOOK_AFTER_ADD, [$m]);

        return $m;
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before model's init().
     */
    protected function initPersistence(Model $m)
    {
    }

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistences will support atomic operations, so by default we just
     * don't do anything.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $fx();
    }

    public function getDatabasePlatform(): Platforms\AbstractPlatform
    {
        return new Persistence\GenericPlatform();
    }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys. Example:.
     *
     * In:
     *  [
     *    'name'=>' John Smith',
     *    'age'=>30,
     *    'password'=>'abc',
     *    'is_married'=>true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name'=>'John Smith',
     *     'age'=>30,
     *     'is_married'=>1
     *   ]
     */
    public function typecastSaveRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // check null values for mandatory fields
            if ($value === null && $field->mandatory) {
                throw new ValidationException([$fieldName => 'Mandatory field value cannot be null']);
            }

            // Expression and null cannot be converted.
            if (
                $value instanceof \Atk4\Dsql\Expression ||
                $value instanceof \Atk4\Dsql\Expressionable ||
                $value === null
            ) {
                $result[$field->getPersistenceName()] = $value;

                continue;
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastSaveField($field, $value);
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeSaveField($field, $value);
            }

            // store converted value
            $result[$field->getPersistenceName()] = $value;
        }

        return $result;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     */
    public function typecastLoadRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // ignore null values
            if ($value === null) {
                $result[$fieldName] = $value;

                continue;
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeLoadField($field, $value);
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastLoadField($field, $value);
            }

            // store converted value
            $result[$fieldName] = $value;
        }

        return $result;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence-friendly format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Field $f, $value)
    {
        try {
            // use $f->typecast = [typecast_save_callback, typecast_load_callback]
            if (is_array($f->typecast) && isset($f->typecast[0]) && ($t = $f->typecast[0]) instanceof \Closure) {
                return $t($value, $f, $this);
            }

            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastSaveField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to typecast field value on save', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastLoadField(Field $f, $value)
    {
        try {
            // use $f->typecast = [typecast_save_callback, typecast_load_callback]
            if (is_array($f->typecast) && isset($f->typecast[1]) && ($t = $f->typecast[1]) instanceof \Closure) {
                return $t($value, $f, $this);
            }

            // only string type fields can use empty string as legit value, for all
            // other field types empty value is the same as no-value, nothing or null
            if ($f->type && $f->type !== 'string' && $value === '') {
                return;
            }

            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastLoadField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to typecast field value on load', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $f, $value)
    {
        return $value;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $f, $value)
    {
        return $value;
    }

    /**
     * Provided with a value, will perform field serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeSaveField(Field $f, $value)
    {
        try {
            // use $f->serialize = [encode_callback, decode_callback]
            if (is_array($f->serialize) && isset($f->serialize[0]) && ($t = $f->serialize[0]) instanceof \Closure) {
                return $t($f, $value, $this);
            }

            // run persistence-specific serialization of field value
            return $this->_serializeSaveField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to serialize field value on save', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Provided with a value, will perform field un-serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeLoadField(Field $f, $value)
    {
        try {
            // use $f->serialize = [encode_callback, decode_callback]
            if (is_array($f->serialize) && isset($f->serialize[1]) && ($t = $f->serialize[1]) instanceof \Closure) {
                return $t($f, $value, $this);
            }

            // run persistence-specific un-serialization of field value
            return $this->_serializeLoadField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to serialize field value on load', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Override this to fine-tune serialization for your persistence.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeSaveField(Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return serialize($value);
        case 'json':
            return $this->jsonEncode($f, $value);
        case 'base64':
            if (!is_string($value)) {
                throw (new Exception('Field value can not be base64 encoded because it is not of string type'))
                    ->addMoreInfo('field', $f)
                    ->addMoreInfo('value', $value);
            }

            return base64_encode($value);
        }
    }

    /**
     * Override this to fine-tune un-serialization for your persistence.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeLoadField(Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return unserialize($value);
        case 'json':
            return $this->jsonDecode($f, $value, $f->type === 'array');
        case 'base64':
            return base64_decode($value, true);
        }
    }

    /**
     * JSON decoding with proper error treatment.
     *
     * @return mixed
     */
    public function jsonDecode(Field $f, string $json, bool $assoc = true)
    {
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON encoding with proper error treatment.
     *
     * @param mixed $value
     */
    public function jsonEncode(Field $f, $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR, 512);
    }
}
