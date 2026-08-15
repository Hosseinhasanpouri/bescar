<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

class ServiceOrder
{
    /** @param list<ServiceOrderItem>|null $items */
    public function __construct(
        public readonly ?int $id,
        public int $user_id,
        public int $vehicle_id,
        public string $service_date,
        public int $odometer,
        public string $total_cost,
        public ?string $notes = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?array $items = null,
        public ?Vehicle $vehicle = null,
    ) {
    }

    public static function fromRow(array $row, ?array $items = null, ?Vehicle $vehicle = null): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            user_id: (int) $row['user_id'],
            vehicle_id: (int) $row['vehicle_id'],
            service_date: (string) $row['service_date'],
            odometer: (int) $row['odometer'],
            total_cost: number_format((float) $row['total_cost'], 2, '.', ''),
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            items: $items,
            vehicle: $vehicle,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'service_date' => $this->service_date,
            'odometer' => $this->odometer,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->items !== null) {
            $data['items'] = array_map(
                static fn (ServiceOrderItem $item): array => $item->toArray(),
                $this->items
            );
        }

        if ($this->vehicle !== null) {
            $data['vehicle'] = $this->vehicle->toArray();
        }

        return $data;
    }

    public static function find(int $id, bool $withRelations = true): ?self
    {
        $stmt = Connection::get()->prepare('SELECT * FROM service_orders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return null;
        }

        $items = $withRelations ? ServiceOrderItem::forOrder($id) : null;
        $vehicle = $withRelations ? Vehicle::find((int) $row['vehicle_id']) : null;

        return self::fromRow($row, $items, $vehicle);
    }

    /** @return list<self> */
    public static function forUser(int $userId, ?int $vehicleId = null): array
    {
        if ($vehicleId !== null) {
            $stmt = Connection::get()->prepare(
                'SELECT * FROM service_orders
                 WHERE user_id = ? AND vehicle_id = ?
                 ORDER BY service_date DESC, id DESC'
            );
            $stmt->execute([$userId, $vehicleId]);
        } else {
            $stmt = Connection::get()->prepare(
                'SELECT * FROM service_orders
                 WHERE user_id = ?
                 ORDER BY service_date DESC, id DESC'
            );
            $stmt->execute([$userId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): self {
            $items = ServiceOrderItem::forOrder((int) $row['id']);
            $vehicle = Vehicle::find((int) $row['vehicle_id']);

            return self::fromRow($row, $items, $vehicle);
        }, $rows);
    }

    /**
     * @param list<array{service_id: int, price: string|float|int, notes?: ?string}> $items
     */
    public static function create(array $data, array $items): self
    {
        $now = utc_now();
        $total = self::sumPrices($items);

        $stmt = Connection::get()->prepare(
            'INSERT INTO service_orders
                (user_id, vehicle_id, service_date, odometer, total_cost, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vehicle_id'],
            $data['service_date'],
            $data['odometer'],
            $total,
            $data['notes'] ?? null,
            $now,
            $now,
        ]);

        $orderId = (int) Connection::get()->lastInsertId();

        foreach ($items as $item) {
            ServiceOrderItem::create([
                'service_order_id' => $orderId,
                'service_id' => $item['service_id'],
                'price' => $item['price'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return self::find($orderId) ?? self::fromRow([
            'id' => $orderId,
            'user_id' => $data['user_id'],
            'vehicle_id' => $data['vehicle_id'],
            'service_date' => $data['service_date'],
            'odometer' => $data['odometer'],
            'total_cost' => $total,
            'notes' => $data['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<array{service_id: int, price: string|float|int, notes?: ?string}> $items
     */
    public function update(array $data, array $items): self
    {
        $now = utc_now();
        $total = self::sumPrices($items);

        $stmt = Connection::get()->prepare(
            'UPDATE service_orders
             SET vehicle_id = ?, service_date = ?, odometer = ?, total_cost = ?, notes = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['vehicle_id'] ?? $this->vehicle_id,
            $data['service_date'] ?? $this->service_date,
            $data['odometer'] ?? $this->odometer,
            $total,
            array_key_exists('notes', $data) ? $data['notes'] : $this->notes,
            $now,
            $this->id,
        ]);

        ServiceOrderItem::deleteForOrder((int) $this->id);

        foreach ($items as $item) {
            ServiceOrderItem::create([
                'service_order_id' => (int) $this->id,
                'service_id' => $item['service_id'],
                'price' => $item['price'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return self::find((int) $this->id) ?? $this;
    }

    public function delete(): bool
    {
        ServiceOrderItem::deleteForOrder((int) $this->id);
        $stmt = Connection::get()->prepare('DELETE FROM service_orders WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    /** @param list<array{price: string|float|int}> $items */
    public static function sumPrices(array $items): string
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['price'];
        }

        return number_format($total, 2, '.', '');
    }
}
