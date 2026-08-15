<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class User
{
    public function __construct(
        public readonly ?int $id,
        public ?string $name,
        public int $phone,
        public ?string $phone_verified_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: $row['name'] !== null ? (string) $row['name'] : null,
            phone: (int) $row['phone'],
            phone_verified_at: $row['phone_verified_at'] ?? null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => (string) $this->phone,
            'phone_verified_at' => iran_datetime($this->phone_verified_at),
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];
    }

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByPhone(int $phone): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO users (name, phone, phone_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['name'] ?? null,
            $data['phone'],
            $data['phone_verified_at'] ?? null,
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return self::find($id) ?? new self(
            id: $id,
            name: isset($data['name']) ? ($data['name'] !== null ? (string) $data['name'] : null) : null,
            phone: (int) $data['phone'],
            phone_verified_at: $data['phone_verified_at'] ?? null,
            created_at: $now,
            updated_at: $now,
        );
    }

    public function update(array $data): self
    {
        $name = array_key_exists('name', $data) ? $data['name'] : $this->name;
        $phone = $data['phone'] ?? $this->phone;
        $phoneVerifiedAt = array_key_exists('phone_verified_at', $data)
            ? $data['phone_verified_at']
            : $this->phone_verified_at;
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'UPDATE users
             SET name = ?, phone = ?, phone_verified_at = ?, updated_at = ?
             WHERE id = ?'
        );

        $stmt->execute([$name, $phone, $phoneVerifiedAt, $now, $this->id]);

        return self::find((int) $this->id) ?? $this;
    }
}
