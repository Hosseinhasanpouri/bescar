<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Document
{
    public const TYPE_OWNER = 'owner';
    public const TYPE_VEHICLE = 'vehicle';

    public function __construct(
        public readonly ?int $id,
        public int $user_id,
        public string $type,
        public string $title,
        public ?int $vehicle_id = null,
        public ?string $expires_at = null,
        public ?string $notes = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?array $vehicle = null,
    ) {
    }

    public static function fromRow(array $row, ?array $vehicle = null): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            user_id: (int) $row['user_id'],
            type: (string) ($row['type'] ?? self::TYPE_OWNER),
            title: (string) $row['title'],
            vehicle_id: isset($row['vehicle_id']) && $row['vehicle_id'] !== null
                ? (int) $row['vehicle_id']
                : null,
            expires_at: $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            vehicle: $vehicle,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'vehicle_id' => $this->vehicle_id,
            'title' => $this->title,
            'expires_at' => $this->expires_at,
            'notes' => $this->notes,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->vehicle !== null) {
            $data['vehicle'] = $this->vehicle;
        }

        return $data;
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT d.*,
                    v.name AS vehicle_name, v.year AS vehicle_year, v.vehicle_model_id,
                    vm.name AS model_name, m.name AS manufactor_name
             FROM documents d
             LEFT JOIN vehicles v ON v.id = d.vehicle_id
             LEFT JOIN vehicle_models vm ON vm.id = v.vehicle_model_id
             LEFT JOIN manufactors m ON m.id = vm.manufactor_id
             WHERE d.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromJoinedRow($row) : null;
    }

    /** @return list<self> */
    public static function forUser(int $userId, ?string $type = null): array
    {
        $sql = 'SELECT d.*,
                       v.name AS vehicle_name, v.year AS vehicle_year, v.vehicle_model_id,
                       vm.name AS model_name, m.name AS manufactor_name
                FROM documents d
                LEFT JOIN vehicles v ON v.id = d.vehicle_id
                LEFT JOIN vehicle_models vm ON vm.id = v.vehicle_model_id
                LEFT JOIN manufactors m ON m.id = vm.manufactor_id
                WHERE d.user_id = ?';
        $params = [$userId];

        if ($type !== null) {
            $sql .= ' AND d.type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY
                    CASE WHEN d.expires_at IS NULL THEN 1 ELSE 0 END ASC,
                    d.expires_at ASC,
                    d.id DESC';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromJoinedRow'], $rows);
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO documents (user_id, type, vehicle_id, title, expires_at, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['type'],
            $data['vehicle_id'] ?? null,
            $data['title'],
            $data['expires_at'] ?? null,
            $data['notes'] ?? null,
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return self::find($id) ?? new self(
            id: $id,
            user_id: (int) $data['user_id'],
            type: (string) $data['type'],
            title: (string) $data['title'],
            vehicle_id: isset($data['vehicle_id']) && $data['vehicle_id'] !== null
                ? (int) $data['vehicle_id']
                : null,
            expires_at: $data['expires_at'] ?? null,
            notes: $data['notes'] ?? null,
            created_at: $now,
            updated_at: $now,
        );
    }

    public function update(array $data): self
    {
        $type = array_key_exists('type', $data) ? $data['type'] : $this->type;
        $vehicleId = array_key_exists('vehicle_id', $data) ? $data['vehicle_id'] : $this->vehicle_id;
        $title = array_key_exists('title', $data) ? $data['title'] : $this->title;
        $expiresAt = array_key_exists('expires_at', $data) ? $data['expires_at'] : $this->expires_at;
        $notes = array_key_exists('notes', $data) ? $data['notes'] : $this->notes;
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'UPDATE documents
             SET type = ?, vehicle_id = ?, title = ?, expires_at = ?, notes = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$type, $vehicleId, $title, $expiresAt, $notes, $now, $this->id]);

        return self::find((int) $this->id) ?? $this;
    }

    public function delete(): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM documents WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    private static function fromJoinedRow(array $row): self
    {
        $vehicle = null;
        if ($row['vehicle_id'] !== null) {
            $labelParts = array_filter([
                $row['manufactor_name'] ?? null,
                $row['model_name'] ?? null,
            ]);
            $fallback = $labelParts !== [] ? implode(' ', $labelParts) : null;

            $vehicle = [
                'id' => (int) $row['vehicle_id'],
                'name' => $row['vehicle_name'] !== null && $row['vehicle_name'] !== ''
                    ? (string) $row['vehicle_name']
                    : $fallback,
                'year' => isset($row['vehicle_year']) && $row['vehicle_year'] !== null
                    ? (int) $row['vehicle_year']
                    : null,
            ];
        }

        return self::fromRow($row, $vehicle);
    }
}
