<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class Otp
{
    public function __construct(
        public readonly ?int $id,
        public int $phone,
        public string $code,
        public string $expires_at,
        public ?string $used_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            phone: (int) $row['phone'],
            code: (string) $row['code'],
            expires_at: (string) $row['expires_at'],
            used_at: $row['used_at'] ?? null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
        );
    }

    public static function createForPhone(int $phone, string $plainCode, int $ttlMinutes): self
    {
        $now = utc_now();
        $expiresAt = utc_from_timestamp(time() + ($ttlMinutes * 60));
        $hash = password_hash($plainCode, PASSWORD_BCRYPT);

        // Invalidate previous unused OTPs for this phone
        $invalidate = Connection::get()->prepare(
            'UPDATE otps SET used_at = ? WHERE phone = ? AND used_at IS NULL'
        );
        $invalidate->execute([$now, $phone]);

        $stmt = Connection::get()->prepare(
            'INSERT INTO otps (phone, code, expires_at, used_at, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, ?)'
        );
        $stmt->execute([$phone, $hash, $expiresAt, $now, $now]);

        $id = (int) Connection::get()->lastInsertId();

        return new self(
            id: $id,
            phone: $phone,
            code: $hash,
            expires_at: $expiresAt,
            created_at: $now,
            updated_at: $now,
        );
    }

    public static function findValid(int $phone): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM otps
             WHERE phone = ? AND used_at IS NULL AND expires_at >= ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$phone, utc_now()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public function matches(string $plainCode): bool
    {
        return password_verify($plainCode, $this->code);
    }

    public function markUsed(): void
    {
        $now = utc_now();
        $stmt = Connection::get()->prepare(
            'UPDATE otps SET used_at = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$now, $now, $this->id]);
    }
}
