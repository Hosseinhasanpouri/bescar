<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class VehicleModel
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public int $manufactor_id,
        public ?string $image = null,
        public array $vehicle_types = [],
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?Manufactor $manufactor = null,
    ) {
    }

    public static function fromRow(array $row, ?Manufactor $manufactor = null): self
    {
        $vt = [];
        if (isset($row['vehicle_types']) && $row['vehicle_types'] !== null && $row['vehicle_types'] !== '') {
            $vt = array_filter(explode(',', (string) $row['vehicle_types']));
        }

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: (string) $row['name'],
            manufactor_id: (int) $row['manufactor_id'],
            image: $row['image'] !== null ? (string) $row['image'] : null,
            vehicle_types: array_values($vt),
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            manufactor: $manufactor,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'manufactor_id' => $this->manufactor_id,
            'image' => $this->image,
            'vehicle_types' => $this->vehicle_types,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->manufactor !== null) {
            $data['manufactor'] = $this->manufactor->toArray();
        }

        return $data;
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT vm.*, m.name AS manufactor_name, m.logo AS manufactor_logo,
                    m.vehicle_types AS manufactor_vehicle_types,
                    m.created_at AS manufactor_created_at, m.updated_at AS manufactor_updated_at
             FROM vehicle_models vm
             INNER JOIN manufactors m ON m.id = vm.manufactor_id
             WHERE vm.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromJoinedRow($row) : null;
    }

    /** @return list<self> */
    public static function all(?int $manufactorId = null, ?string $vehicleType = null): array
    {
        $where = [];
        $params = [];

        if ($manufactorId !== null) {
            $where[] = 'vm.manufactor_id = ?';
            $params[] = $manufactorId;
        }

        if ($vehicleType !== null) {
            $where[] = 'FIND_IN_SET(?, vm.vehicle_types) > 0';
            $params[] = $vehicleType;
        }

        $sql = 'SELECT vm.*, m.name AS manufactor_name, m.logo AS manufactor_logo,
                       m.vehicle_types AS manufactor_vehicle_types,
                       m.created_at AS manufactor_created_at, m.updated_at AS manufactor_updated_at
                FROM vehicle_models vm
                INNER JOIN manufactors m ON m.id = vm.manufactor_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= $manufactorId !== null
            ? ' ORDER BY vm.name ASC'
            : ' ORDER BY m.name ASC, vm.name ASC';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromJoinedRow'], $rows);
    }

    private static function fromJoinedRow(array $row): self
    {
        $manufactor = Manufactor::fromRow([
            'id' => $row['manufactor_id'],
            'name' => $row['manufactor_name'],
            'logo' => $row['manufactor_logo'],
            'vehicle_types' => $row['manufactor_vehicle_types'] ?? null,
            'created_at' => $row['manufactor_created_at'],
            'updated_at' => $row['manufactor_updated_at'],
        ]);

        return self::fromRow($row, $manufactor);
    }
}
