<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class ServiceOrderItem
{
    public function __construct(
        public readonly ?int $id,
        public int $service_order_id,
        public int $service_id,
        public string $price,
        public ?string $notes = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?Service $service = null,
    ) {
    }

    public static function fromRow(array $row, ?Service $service = null): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            service_order_id: (int) $row['service_order_id'],
            service_id: (int) $row['service_id'],
            price: number_format((float) $row['price'], 2, '.', ''),
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            service: $service,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'service_order_id' => $this->service_order_id,
            'service_id' => $this->service_id,
            'price' => $this->price,
            'notes' => $this->notes,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->service !== null) {
            $data['service'] = $this->service->toArray();
        }

        return $data;
    }

    /** @return list<self> */
    public static function forOrder(int $orderId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT i.*, s.name AS service_name, s.slug AS service_slug,
                    s.description AS service_description, s.is_active AS service_is_active,
                    s.created_at AS service_created_at, s.updated_at AS service_updated_at
             FROM service_order_items i
             INNER JOIN services s ON s.id = i.service_id
             WHERE i.service_order_id = ?
             ORDER BY i.id ASC'
        );
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): self {
            $service = Service::fromRow([
                'id' => $row['service_id'],
                'name' => $row['service_name'],
                'slug' => $row['service_slug'],
                'description' => $row['service_description'],
                'is_active' => $row['service_is_active'],
                'created_at' => $row['service_created_at'],
                'updated_at' => $row['service_updated_at'],
            ]);

            return self::fromRow($row, $service);
        }, $rows);
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO service_order_items (service_order_id, service_id, price, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['service_order_id'],
            $data['service_id'],
            $data['price'],
            $data['notes'] ?? null,
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return new self(
            id: $id,
            service_order_id: (int) $data['service_order_id'],
            service_id: (int) $data['service_id'],
            price: number_format((float) $data['price'], 2, '.', ''),
            notes: $data['notes'] ?? null,
            created_at: $now,
            updated_at: $now,
        );
    }

    public static function deleteForOrder(int $orderId): void
    {
        $stmt = Connection::get()->prepare('DELETE FROM service_order_items WHERE service_order_id = ?');
        $stmt->execute([$orderId]);
    }
}
