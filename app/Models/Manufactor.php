<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Manufactor
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public ?string $logo = null,
        public array $vehicle_types = [],
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $vt = [];
        if (isset($row['vehicle_types']) && $row['vehicle_types'] !== null && $row['vehicle_types'] !== '') {
            $vt = array_filter(explode(',', (string) $row['vehicle_types']));
        }

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: (string) $row['name'],
            logo: $row['logo'] !== null ? (string) $row['logo'] : null,
            vehicle_types: array_values($vt),
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => $this->logo,
            'vehicle_types' => $this->vehicle_types,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM manufactors WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    /** @return list<self> */
    public static function all(?string $vehicleType = null): array
    {
        if ($vehicleType !== null) {
            $stmt = Connection::get()->prepare(
                "SELECT * FROM manufactors WHERE FIND_IN_SET(?, vehicle_types) > 0 ORDER BY name ASC"
            );
            $stmt->execute([$vehicleType]);
        } else {
            $stmt = Connection::get()->query('SELECT * FROM manufactors ORDER BY name ASC');
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromRow'], $rows);
    }
}
