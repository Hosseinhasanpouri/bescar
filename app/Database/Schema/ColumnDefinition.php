<?php

declare(strict_types=1);

namespace App\Database\Schema;

class ColumnDefinition
{
    private bool $nullable = false;
    private bool $unique = false;
    private mixed $default = null;
    private bool $hasDefault = false;

    public function __construct(
        private readonly string $name,
        private string $type,
        private readonly Blueprint $blueprint,
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        if ($this->nullable) {
            $sql = preg_replace('/\s+NOT NULL\b/', '', $sql) ?? $sql;
            if (! preg_match('/\bNULL\b/i', $sql)) {
                $sql .= ' NULL';
            }
        }

        if ($this->hasDefault) {
            if ($this->default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($this->default)) {
                $sql .= ' DEFAULT ' . ($this->default ? '1' : '0');
            } elseif (is_int($this->default) || is_float($this->default)) {
                $sql .= ' DEFAULT ' . $this->default;
            } else {
                $sql .= " DEFAULT '" . addslashes((string) $this->default) . "'";
            }
        }

        return $sql;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function name(): string
    {
        return $this->name;
    }
}
