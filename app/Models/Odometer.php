<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Odometer
{
    public function __construct(
        public readonly ?int $id,
        public int $user_id,
        public int $vehicle_id,
        public int $value,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            user_id: (int) $row['user_id'],
            vehicle_id: (int) $row['vehicle_id'],
            value: (int) $row['value'],
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'value' => $this->value,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM odometer WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function latestForVehicle(int $vehicleId): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM odometer WHERE vehicle_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$vehicleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    /** @return list<self> */
    public static function forUser(int $userId, ?int $vehicleId = null): array
    {
        if ($vehicleId !== null) {
            $stmt = Connection::get()->prepare(
                'SELECT * FROM odometer WHERE user_id = ? AND vehicle_id = ? ORDER BY id DESC'
            );
            $stmt->execute([$userId, $vehicleId]);
        } else {
            $stmt = Connection::get()->prepare(
                'SELECT * FROM odometer WHERE user_id = ? ORDER BY id DESC'
            );
            $stmt->execute([$userId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO odometer (user_id, vehicle_id, value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vehicle_id'],
            $data['value'],
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return self::find($id) ?? new self(
            id: $id,
            user_id: (int) $data['user_id'],
            vehicle_id: (int) $data['vehicle_id'],
            value: (int) $data['value'],
            created_at: $now,
            updated_at: $now,
        );
    }
}
