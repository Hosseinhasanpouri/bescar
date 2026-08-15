<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Service
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public string $slug,
        public ?string $description = null,
        public bool $is_active = true,
        public ?int $default_interval_km = null,
        public ?int $default_interval_months = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            is_active: (bool) ($row['is_active'] ?? true),
            default_interval_km: isset($row['default_interval_km']) && $row['default_interval_km'] !== null
                ? (int) $row['default_interval_km']
                : null,
            default_interval_months: isset($row['default_interval_months']) && $row['default_interval_months'] !== null
                ? (int) $row['default_interval_months']
                : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'default_interval_km' => $this->default_interval_km,
            'default_interval_months' => $this->default_interval_months,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];
    }

    public function hasDefaultInterval(): bool
    {
        return $this->default_interval_km !== null || $this->default_interval_months !== null;
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function findBySlug(string $slug): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM services WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    /** @return list<self> */
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM services';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = Connection::get()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO services
                (name, slug, description, is_active, default_interval_km, default_interval_months, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            ! empty($data['is_active']) ? 1 : 0,
            $data['default_interval_km'] ?? null,
            $data['default_interval_months'] ?? null,
            $now,
            $now,
        ]);

        return self::find((int) Connection::get()->lastInsertId()) ?? new self(
            id: (int) Connection::get()->lastInsertId(),
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            description: $data['description'] ?? null,
            is_active: ! empty($data['is_active']),
            default_interval_km: $data['default_interval_km'] ?? null,
            default_interval_months: $data['default_interval_months'] ?? null,
            created_at: $now,
            updated_at: $now,
        );
    }

    public function update(array $data): self
    {
        $name = array_key_exists('name', $data) ? $data['name'] : $this->name;
        $slug = array_key_exists('slug', $data) ? $data['slug'] : $this->slug;
        $description = array_key_exists('description', $data) ? $data['description'] : $this->description;
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $this->is_active;
        $defaultIntervalKm = array_key_exists('default_interval_km', $data)
            ? $data['default_interval_km']
            : $this->default_interval_km;
        $defaultIntervalMonths = array_key_exists('default_interval_months', $data)
            ? $data['default_interval_months']
            : $this->default_interval_months;
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'UPDATE services
             SET name = ?, slug = ?, description = ?, is_active = ?,
                 default_interval_km = ?, default_interval_months = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $name,
            $slug,
            $description,
            $isActive ? 1 : 0,
            $defaultIntervalKm,
            $defaultIntervalMonths,
            $now,
            $this->id,
        ]);

        return self::find((int) $this->id) ?? $this;
    }

    public function delete(): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM services WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'service';
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = self::slugify($base);
        $candidate = $slug;
        $i = 2;

        while (true) {
            $existing = self::findBySlug($candidate);
            if ($existing === null || ($ignoreId !== null && $existing->id === $ignoreId)) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
            $i++;
        }
    }
}
