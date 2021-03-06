<?php

declare(strict_types=1);

namespace Atk4\Schema;

use Atk4\Core\Exception;
use Atk4\Data\Field;
use Atk4\Data\FieldSqlExpression;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Reference\HasOne;
use Atk4\Dsql\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;

class Migration
{
    public const REF_TYPE_NONE = 0;
    public const REF_TYPE_LINK = 1;
    public const REF_TYPE_PRIMARY = 2;

    /** @var Connection */
    public $connection;

    /** @var Table */
    public $table;

    /**
     * Create new migration.
     *
     * @param Connection|Persistence|Model $source
     */
    public function __construct($source)
    {
        if (func_num_args() > 1) {
            throw new \Error();
        }

        if ($source instanceof Connection) {
            $this->connection = $source;
        } elseif ($source instanceof Persistence\Sql) {
            $this->connection = $source->connection;
        } elseif ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->connection = $source->persistence->connection;
        } else {
            throw (new Exception('Source is specified incorrectly. Must be Connection, Persistence or initialized Model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->setModel($source);
        }
    }

    protected function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    protected function getSchemaManager(): AbstractSchemaManager
    {
        return $this->connection->connection()->getSchemaManager();
    }

    public function table($tableName): self
    {
        $this->table = new Table($this->getDatabasePlatform()->quoteSingleIdentifier($tableName));

        return $this;
    }

    public function create(): self
    {
        $this->getSchemaManager()->createTable($this->table);

        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->connection->expr(
                <<<'EOT'
                    begin
                        execute immediate [];
                    end;
                    EOT,
                [
                    $this->connection->expr(
                        <<<'EOT'
                            create or replace trigger {table_ai_trigger_before}
                                before insert on {table}
                                for each row
                                when (new."id" is null)
                            declare
                                last_id {table}."id"%type;
                            begin
                                select nvl(max("id"), 0) into last_id from {table};
                                :new."id" := last_id + 1;
                            end;
                            EOT,
                        [
                            'table' => $this->table->getName(),
                            'table_ai_trigger_before' => $this->table->getName() . '_ai_trigger_before',
                        ]
                    )->render(),
                ]
            )->execute();
        }

        return $this;
    }

    public function drop(): self
    {
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            // drop trigger if exists
            // see https://stackoverflow.com/questions/1799128/oracle-if-table-exists
            $this->connection->expr(
                <<<'EOT'
                    begin
                        execute immediate [];
                    exception
                        when others then
                            if sqlcode != -4080 then
                                raise;
                            end if;
                    end;
                    EOT,
                [
                    $this->connection->expr(
                        'drop trigger {table_ai_trigger_before}',
                        [
                            'table_ai_trigger_before' => $this->table->getName() . '_ai_trigger_before',
                        ]
                    )->render(),
                ]
            )->execute();
        }

        $this->getSchemaManager()->dropTable($this->getDatabasePlatform()->quoteSingleIdentifier($this->table->getName()));

        return $this;
    }

    public function dropIfExists(): self
    {
        try {
            $this->drop();
        } catch (\Doctrine\DBAL\Exception | \Doctrine\DBAL\DBALException $e) { // @phpstan-ignore-line for DBAL 2.x
        }

        return $this;
    }

    public function field(string $fieldName, $options = []): self
    {
        // TODO remove once we no longer support "money" database type
        if (($options['type'] ?? null) === 'money') {
            $options['type'] = 'float';
        }

        $refType = $options['ref_type'] ?? self::REF_TYPE_NONE;
        unset($options['ref_type']);

        $column = $this->table->addColumn($this->getDatabasePlatform()->quoteSingleIdentifier($fieldName), $options['type'] ?? 'string');

        if (!($options['mandatory'] ?? false) && $refType !== self::REF_TYPE_PRIMARY) {
            $column->setNotnull(false);
        }

        if ($column->getType()->getName() === 'integer' && $refType !== self::REF_TYPE_NONE) {
            $column->setUnsigned(true);
        }

        if (in_array($column->getType()->getName(), ['string', 'text'], true)) {
            if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
                $column->setPlatformOption('collation', 'NOCASE');
            }
        }

        if ($refType === self::REF_TYPE_PRIMARY) {
            $this->table->setPrimaryKey([$this->getDatabasePlatform()->quoteSingleIdentifier($fieldName)]);
            if (!$this->getDatabasePlatform() instanceof OraclePlatform) {
                $column->setAutoincrement(true);
            }
        }

        return $this;
    }

    public function id(string $name = 'id'): self
    {
        $options = [
            'type' => 'integer',
            'ref_type' => self::REF_TYPE_PRIMARY,
        ];

        $this->field($name, $options);

        return $this;
    }

    public function setModel(Model $model): Model
    {
        $this->table($model->table);

        foreach ($model->getFields() as $field) {
            if ($field->never_persist || $field instanceof FieldSqlExpression) {
                continue;
            }

            if ($field->short_name === $model->id_field) {
                $refype = self::REF_TYPE_PRIMARY;
                $persistField = $field;
            } else {
                $refField = $this->getReferenceField($field);
                $refype = $refField !== null ? self::REF_TYPE_LINK : $refype = self::REF_TYPE_NONE;
                $persistField = $refField ?? $field;
            }

            $options = [
                'type' => $refype !== self::REF_TYPE_NONE && empty($persistField->type) ? 'integer' : $persistField->type,
                'ref_type' => $refype,
                'mandatory' => ($field->mandatory || $field->required) && ($persistField->mandatory || $persistField->required),
            ];

            $this->field($field->actual ?: $field->short_name, $options);
        }

        return $model;
    }

    protected function getReferenceField(Field $field): ?Field
    {
        if ($field->reference instanceof HasOne) {
            $referenceTheirField = \Closure::bind(function () use ($field) {
                return $field->reference->their_field;
            }, null, \Atk4\Data\Reference::class)();

            $referenceField = $referenceTheirField ?? $field->reference->getOwner()->id_field;

            $modelSeed = is_array($field->reference->model)
                ? $field->reference->model
                : [get_class($field->reference->model)];
            $referenceModel = Model::fromSeed($modelSeed, [new Persistence\Sql($this->connection)]);

            return $referenceModel->getField($referenceField);
        }

        return null;
    }
}
