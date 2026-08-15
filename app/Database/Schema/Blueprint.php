<?php

declare(strict_types=1);

namespace App\Database\Schema;

class Blueprint
{
    /** @var list<string|ColumnDefinition> */
    private array $columns = [];

    /** @var list<string> */
    private array $indexes = [];

    public function __construct(
        public readonly string $table,
    ) {
    }

    public function id(string $column = 'id'): self
    {
        $this->columns[] = "`{$column}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";

        return $this;
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, "VARCHAR({$length}) NOT NULL");
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TEXT NOT NULL');
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'INT NOT NULL');
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'INT UNSIGNED NOT NULL');
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BIGINT NOT NULL');
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BIGINT UNSIGNED NOT NULL');
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TINYINT(1) NOT NULL');
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TIMESTAMP NULL');
    }

    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'DATETIME NOT NULL');
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'DATE NOT NULL');
    }

    public function decimal(string $column, int $precision = 12, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($column, "DECIMAL({$precision},{$scale}) NOT NULL");
    }

    public function timestamps(): self
    {
        $this->columns[] = '`created_at` TIMESTAMP NULL DEFAULT NULL';
        $this->columns[] = '`updated_at` TIMESTAMP NULL DEFAULT NULL';

        return $this;
    }

    public function softDeletes(): self
    {
        $this->columns[] = '`deleted_at` TIMESTAMP NULL DEFAULT NULL';

        return $this;
    }

    public function unique(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $name = $this->table . '_' . implode('_', $cols) . '_unique';
        $list = implode('`, `', $cols);
        $this->indexes[] = "UNIQUE KEY `{$name}` (`{$list}`)";

        return $this;
    }

    public function index(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $name = $this->table . '_' . implode('_', $cols) . '_index';
        $list = implode('`, `', $cols);
        $this->indexes[] = "KEY `{$name}` (`{$list}`)";

        return $this;
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function foreign(
        string $column,
        string $on,
        string $references = 'id',
        string $onDelete = 'CASCADE',
    ): self {
        $this->indexes[] = sprintf(
            'CONSTRAINT `%s_%s_foreign` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
            $this->table,
            $column,
            $column,
            $on,
            $references,
            $onDelete,
        );

        return $this;
    }

    private function addColumn(string $column, string $definition): ColumnDefinition
    {
        $def = new ColumnDefinition($column, $definition, $this);
        $this->columns[] = $def;

        return $def;
    }

    public function toSql(): string
    {
        $parts = [];

        foreach ($this->columns as $column) {
            if ($column instanceof ColumnDefinition) {
                $parts[] = $column->toSql();

                if ($column->isUnique()) {
                    $name = $this->table . '_' . $column->name() . '_unique';
                    $this->indexes[] = "UNIQUE KEY `{$name}` (`{$column->name()}`)";
                }
            } else {
                $parts[] = $column;
            }
        }

        $parts = array_merge($parts, $this->indexes);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n  %s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            $this->table,
            implode(",\n  ", $parts)
        );
    }
}
