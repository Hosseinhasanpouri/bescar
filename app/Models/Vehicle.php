<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Vehicle
{
    public const TYPE_CAR = 'car';
    public const TYPE_TRUCK = 'truck';
    public const TYPE_MOTORCYCLE = 'motorcycle';

    public const PLATE_NATIONAL = 'national';
    public const PLATE_FREE_ZONE = 'free_zone';
    public const PLATE_MOTORCYCLE = 'motorcycle';

    public const VALID_TYPES = [self::TYPE_CAR, self::TYPE_TRUCK, self::TYPE_MOTORCYCLE];
    public const VALID_PLATE_TYPES = [self::PLATE_NATIONAL, self::PLATE_FREE_ZONE, self::PLATE_MOTORCYCLE];

    // Persian + Iran-specific plate letters
    public const PLATE_ALPHABETS = [
        'الف', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ',
        'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض',
        'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل',
        'م', 'ن', 'و', 'ه', 'ی',
        'معلول', 'تشریفات', 'D', 'S',
    ];

    public function __construct(
        public readonly ?int $id,
        public int $user_id,
        public int $vehicle_model_id,
        public ?string $name = null,
        public ?int $year = null,
        public ?string $vin = null,
        public ?string $vehicle_type = null,
        public ?string $plate_type = null,
        public ?string $plate = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?VehicleModel $vehicle_model = null,
    ) {
    }

    public static function fromRow(array $row, ?VehicleModel $vehicleModel = null): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            user_id: (int) $row['user_id'],
            vehicle_model_id: (int) $row['vehicle_model_id'],
            name: $row['name'] !== null ? (string) $row['name'] : null,
            year: isset($row['year']) && $row['year'] !== null ? (int) $row['year'] : null,
            vin: isset($row['vin']) && $row['vin'] !== null ? (string) $row['vin'] : null,
            vehicle_type: isset($row['vehicle_type']) && $row['vehicle_type'] !== null
                ? (string) $row['vehicle_type']
                : null,
            plate_type: isset($row['plate_type']) && $row['plate_type'] !== null
                ? (string) $row['plate_type']
                : null,
            plate: isset($row['plate']) && $row['plate'] !== null
                ? (string) $row['plate']
                : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            vehicle_model: $vehicleModel,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vehicle_model_id' => $this->vehicle_model_id,
            'name' => $this->name,
            'year' => $this->year,
            'vin' => $this->vin,
            'vehicle_type' => $this->vehicle_type,
            'plate_type' => $this->plate_type,
            'plate' => $this->plate,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->vehicle_model !== null) {
            $data['vehicle_model'] = $this->vehicle_model->toArray();
        }

        return $data;
    }

    /**
     * Validate plate format against type.
     * national:   "99 H 999 99"  → two digits, space, letter(s), space, three digits, space, two digits
     * free_zone:  "99999 99"     → five digits, space, two digits
     * motorcycle: "999 99999"    → three digits, space, five digits
     */
    public static function validatePlate(string $plateType, string $plate): bool
    {
        $plateAlphabetPattern = implode('|', array_map(
            static fn (string $a): string => preg_quote($a, '/'),
            self::PLATE_ALPHABETS,
        ));

        return match ($plateType) {
            self::PLATE_NATIONAL => (bool) preg_match(
                '/^\d{2} (' . $plateAlphabetPattern . ') \d{3} \d{2}$/u',
                $plate
            ),
            self::PLATE_FREE_ZONE => (bool) preg_match('/^\d{5} \d{2}$/', $plate),
            self::PLATE_MOTORCYCLE => (bool) preg_match('/^\d{3} \d{5}$/', $plate),
            default => false,
        };
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT v.*,
                    vm.name AS model_name, vm.image AS model_image, vm.manufactor_id,
                    vm.created_at AS model_created_at, vm.updated_at AS model_updated_at,
                    m.name AS manufactor_name, m.logo AS manufactor_logo,
                    m.created_at AS manufactor_created_at, m.updated_at AS manufactor_updated_at
             FROM vehicles v
             INNER JOIN vehicle_models vm ON vm.id = v.vehicle_model_id
             INNER JOIN manufactors m ON m.id = vm.manufactor_id
             WHERE v.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromJoinedRow($row) : null;
    }

    /** @return list<self> */
    public static function forUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT v.*,
                    vm.name AS model_name, vm.image AS model_image, vm.manufactor_id,
                    vm.created_at AS model_created_at, vm.updated_at AS model_updated_at,
                    m.name AS manufactor_name, m.logo AS manufactor_logo,
                    m.created_at AS manufactor_created_at, m.updated_at AS manufactor_updated_at
             FROM vehicles v
             INNER JOIN vehicle_models vm ON vm.id = v.vehicle_model_id
             INNER JOIN manufactors m ON m.id = vm.manufactor_id
             WHERE v.user_id = ?
             ORDER BY v.id DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromJoinedRow'], $rows);
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO vehicles
                (user_id, vehicle_model_id, name, year, vin, vehicle_type, plate_type, plate, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vehicle_model_id'],
            $data['name'] ?? null,
            $data['year'] ?? null,
            $data['vin'] ?? null,
            $data['vehicle_type'] ?? null,
            $data['plate_type'] ?? null,
            $data['plate'] ?? null,
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return self::find($id) ?? new self(
            id: $id,
            user_id: (int) $data['user_id'],
            vehicle_model_id: (int) $data['vehicle_model_id'],
            name: isset($data['name']) ? ($data['name'] !== null ? (string) $data['name'] : null) : null,
            year: isset($data['year']) && $data['year'] !== null ? (int) $data['year'] : null,
            vin: isset($data['vin']) && $data['vin'] !== null ? (string) $data['vin'] : null,
            vehicle_type: $data['vehicle_type'] ?? null,
            plate_type: $data['plate_type'] ?? null,
            plate: $data['plate'] ?? null,
            created_at: $now,
            updated_at: $now,
        );
    }

    public function update(array $data): self
    {
        $vehicleModelId = $data['vehicle_model_id'] ?? $this->vehicle_model_id;
        $name = array_key_exists('name', $data) ? $data['name'] : $this->name;
        $year = array_key_exists('year', $data) ? $data['year'] : $this->year;
        $vin = array_key_exists('vin', $data) ? $data['vin'] : $this->vin;
        $vehicleType = array_key_exists('vehicle_type', $data) ? $data['vehicle_type'] : $this->vehicle_type;
        $plateType = array_key_exists('plate_type', $data) ? $data['plate_type'] : $this->plate_type;
        $plate = array_key_exists('plate', $data) ? $data['plate'] : $this->plate;
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'UPDATE vehicles
             SET vehicle_model_id = ?, name = ?, year = ?, vin = ?,
                 vehicle_type = ?, plate_type = ?, plate = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $vehicleModelId, $name, $year, $vin,
            $vehicleType, $plateType, $plate, $now,
            $this->id,
        ]);

        return self::find((int) $this->id) ?? $this;
    }

    public function delete(): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM vehicles WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    private static function fromJoinedRow(array $row): self
    {
        $manufactor = Manufactor::fromRow([
            'id' => $row['manufactor_id'],
            'name' => $row['manufactor_name'],
            'logo' => $row['manufactor_logo'],
            'created_at' => $row['manufactor_created_at'],
            'updated_at' => $row['manufactor_updated_at'],
        ]);

        $model = VehicleModel::fromRow([
            'id' => $row['vehicle_model_id'],
            'name' => $row['model_name'],
            'manufactor_id' => $row['manufactor_id'],
            'image' => $row['model_image'],
            'created_at' => $row['model_created_at'],
            'updated_at' => $row['model_updated_at'],
        ], $manufactor);

        return self::fromRow($row, $model);
    }
}
