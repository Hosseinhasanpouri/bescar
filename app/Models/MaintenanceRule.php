<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

class MaintenanceRule
{
    public function __construct(
        public readonly ?int $id,
        public int $user_id,
        public int $vehicle_id,
        public int $service_id,
        public ?int $interval_km = null,
        public ?int $interval_months = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?Service $service = null,
        public ?Vehicle $vehicle = null,
    ) {
    }

    public static function fromRow(array $row, ?Service $service = null, ?Vehicle $vehicle = null): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            user_id: (int) $row['user_id'],
            vehicle_id: (int) $row['vehicle_id'],
            service_id: (int) $row['service_id'],
            interval_km: isset($row['interval_km']) && $row['interval_km'] !== null
                ? (int) $row['interval_km']
                : null,
            interval_months: isset($row['interval_months']) && $row['interval_months'] !== null
                ? (int) $row['interval_months']
                : null,
            created_at: $row['created_at'] ?? null,
            updated_at: $row['updated_at'] ?? null,
            service: $service,
            vehicle: $vehicle,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'service_id' => $this->service_id,
            'interval_km' => $this->interval_km,
            'interval_months' => $this->interval_months,
            'created_at' => iran_datetime($this->created_at),
            'updated_at' => iran_datetime($this->updated_at),
        ];

        if ($this->service !== null) {
            $data['service'] = $this->service->toArray();
        }

        if ($this->vehicle !== null) {
            $data['vehicle'] = $this->vehicle->toArray();
        }

        return $data;
    }

    private const SERVICE_SELECT = 's.name AS service_name, s.slug AS service_slug,
                    s.description AS service_description, s.is_active AS service_is_active,
                    s.default_interval_km AS service_default_interval_km,
                    s.default_interval_months AS service_default_interval_months,
                    s.created_at AS service_created_at, s.updated_at AS service_updated_at';

    public static function find(int $id): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT mr.*, ' . self::SERVICE_SELECT . '
             FROM maintenance_rules mr
             LEFT JOIN services s ON s.id = mr.service_id
             WHERE mr.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromJoinedRow($row) : null;
    }

    /** @return list<self> */
    public static function forUser(int $userId, ?int $vehicleId = null): array
    {
        $sql = 'SELECT mr.*, ' . self::SERVICE_SELECT . '
                FROM maintenance_rules mr
                LEFT JOIN services s ON s.id = mr.service_id
                WHERE mr.user_id = ?';
        $params = [$userId];

        if ($vehicleId !== null) {
            $sql .= ' AND mr.vehicle_id = ?';
            $params[] = $vehicleId;
        }

        $sql .= ' ORDER BY s.name ASC, mr.id DESC';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'fromJoinedRow'], $rows);
    }

    /**
     * Custom rules plus virtual rules from service defaults for one vehicle.
     *
     * @return list<self>
     */
    public static function effectiveForVehicle(int $userId, int $vehicleId): array
    {
        $customByService = [];
        foreach (self::forUser($userId, $vehicleId) as $rule) {
            $customByService[(int) $rule->service_id] = $rule;
        }

        $result = [];
        $seen = [];

        foreach (Service::all(true) as $service) {
            if ($service->id === null) {
                continue;
            }

            $serviceId = (int) $service->id;
            if (isset($customByService[$serviceId])) {
                $result[] = $customByService[$serviceId];
                $seen[$serviceId] = true;
                continue;
            }

            if (! $service->hasDefaultInterval()) {
                continue;
            }

            $result[] = new self(
                id: null,
                user_id: $userId,
                vehicle_id: $vehicleId,
                service_id: $serviceId,
                interval_km: $service->default_interval_km,
                interval_months: $service->default_interval_months,
                service: $service,
            );
            $seen[$serviceId] = true;
        }

        // Keep customized rules for inactive / no-default services.
        foreach ($customByService as $serviceId => $rule) {
            if (! isset($seen[$serviceId])) {
                $result[] = $rule;
            }
        }

        usort($result, static function (self $a, self $b): int {
            $aName = $a->service?->name ?? '';
            $bName = $b->service?->name ?? '';

            return strcasecmp($aName, $bName);
        });

        return $result;
    }

    /**
     * Effective rules across all of a user's vehicles.
     *
     * @return list<self>
     */
    public static function effectiveForUser(int $userId): array
    {
        $result = [];
        foreach (Vehicle::forUser($userId) as $vehicle) {
            if ($vehicle->id === null) {
                continue;
            }
            foreach (self::effectiveForVehicle($userId, (int) $vehicle->id) as $rule) {
                $result[] = $rule;
            }
        }

        return $result;
    }

    public static function findForVehicleService(int $vehicleId, int $serviceId): ?self
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM maintenance_rules WHERE vehicle_id = ? AND service_id = ? LIMIT 1'
        );
        $stmt->execute([$vehicleId, $serviceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function create(array $data): self
    {
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'INSERT INTO maintenance_rules
                (user_id, vehicle_id, service_id, interval_km, interval_months, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vehicle_id'],
            $data['service_id'],
            $data['interval_km'] ?? null,
            $data['interval_months'] ?? null,
            $now,
            $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return self::find($id) ?? self::fromRow([
            'id' => $id,
            'user_id' => $data['user_id'],
            'vehicle_id' => $data['vehicle_id'],
            'service_id' => $data['service_id'],
            'interval_km' => $data['interval_km'] ?? null,
            'interval_months' => $data['interval_months'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(array $data): self
    {
        $serviceId = array_key_exists('service_id', $data) ? $data['service_id'] : $this->service_id;
        $intervalKm = array_key_exists('interval_km', $data) ? $data['interval_km'] : $this->interval_km;
        $intervalMonths = array_key_exists('interval_months', $data)
            ? $data['interval_months']
            : $this->interval_months;
        $now = utc_now();

        $stmt = Connection::get()->prepare(
            'UPDATE maintenance_rules
             SET service_id = ?, interval_km = ?, interval_months = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$serviceId, $intervalKm, $intervalMonths, $now, $this->id]);

        return self::find((int) $this->id) ?? $this;
    }

    public function delete(): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM maintenance_rules WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    /**
     * Last completed service order for this vehicle + service.
     *
     * @return array{service_order_id: int, service_date: string, odometer: int}|null
     */
    public static function lastServiceFor(int $vehicleId, int $serviceId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT so.id AS service_order_id, so.service_date, so.odometer
             FROM service_order_items soi
             INNER JOIN service_orders so ON so.id = soi.service_order_id
             WHERE so.vehicle_id = ? AND soi.service_id = ?
             ORDER BY so.service_date DESC, so.id DESC
             LIMIT 1'
        );
        $stmt->execute([$vehicleId, $serviceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return null;
        }

        return [
            'service_order_id' => (int) $row['service_order_id'],
            'service_date' => (string) $row['service_date'],
            'odometer' => (int) $row['odometer'],
        ];
    }

    /**
     * Build remaining / next-due status for this rule.
     *
     * @return array<string, mixed>
     */
    public function status(?int $currentOdometer = null): array
    {
        $last = self::lastServiceFor($this->vehicle_id, $this->service_id);
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran'));

        $nextDueOdometer = null;
        $remainingKm = null;
        $nextDueDate = null;
        $remainingDays = null;
        $dueByKm = false;
        $dueByTime = false;

        if ($last !== null && $this->interval_km !== null) {
            $nextDueOdometer = $last['odometer'] + $this->interval_km;
            if ($currentOdometer !== null) {
                $remainingKm = $nextDueOdometer - $currentOdometer;
                $dueByKm = $remainingKm <= 0;
            }
        }

        if ($last !== null && $this->interval_months !== null) {
            $base = new DateTimeImmutable($last['service_date'], new DateTimeZone('Asia/Tehran'));
            $nextDue = $base->add(new DateInterval('P' . $this->interval_months . 'M'));
            $nextDueDate = $nextDue->format('Y-m-d');
            $remainingDays = (int) $today->diff($nextDue)->format('%r%a');
            $dueByTime = $remainingDays <= 0;
        }

        $isDue = false;
        if ($last !== null) {
            if ($this->interval_km !== null && $this->interval_months !== null) {
                $isDue = $dueByKm || $dueByTime;
            } elseif ($this->interval_km !== null) {
                $isDue = $dueByKm;
            } elseif ($this->interval_months !== null) {
                $isDue = $dueByTime;
            }
        }

        $progressKm = null;
        if ($last !== null && $this->interval_km !== null && $this->interval_km > 0 && $remainingKm !== null) {
            $progressKm = min(100.0, max(0.0, round(($remainingKm / $this->interval_km) * 100, 1)));
        }

        $progressTime = null;
        if ($last !== null && $this->interval_months !== null && $this->interval_months > 0 && $remainingDays !== null && $nextDueDate !== null) {
            $base = new DateTimeImmutable($last['service_date'], new DateTimeZone('Asia/Tehran'));
            $nextDue = new DateTimeImmutable($nextDueDate, new DateTimeZone('Asia/Tehran'));
            $totalDays = max(1, (int) $base->diff($nextDue)->format('%a'));
            $progressTime = min(100.0, max(0.0, round(($remainingDays / $totalDays) * 100, 1)));
        }

        $progress = null;
        if ($progressKm !== null && $progressTime !== null) {
            // Whichever runs out first (lower remaining %) drives the bar.
            $progress = min($progressKm, $progressTime);
        } elseif ($progressKm !== null) {
            $progress = $progressKm;
        } elseif ($progressTime !== null) {
            $progress = $progressTime;
        }

        return [
            'rule' => $this->toArray(),
            'last_service' => $last,
            'current_odometer' => $currentOdometer,
            'next_due_odometer' => $nextDueOdometer,
            'remaining_km' => $remainingKm,
            'next_due_date' => $nextDueDate,
            'remaining_days' => $remainingDays,
            'is_due' => $isDue,
            'never_serviced' => $last === null,
            'is_default' => $this->id === null,
            'progress_km' => $progressKm,
            'progress_time' => $progressTime,
            'progress' => $progress,
        ];
    }

    private static function fromJoinedRow(array $row): self
    {
        $service = null;
        if (isset($row['service_name']) && $row['service_name'] !== null) {
            $service = Service::fromRow([
                'id' => $row['service_id'],
                'name' => $row['service_name'],
                'slug' => $row['service_slug'],
                'description' => $row['service_description'],
                'is_active' => $row['service_is_active'],
                'default_interval_km' => $row['service_default_interval_km'] ?? null,
                'default_interval_months' => $row['service_default_interval_months'] ?? null,
                'created_at' => $row['service_created_at'],
                'updated_at' => $row['service_updated_at'],
            ]);
        }

        return self::fromRow($row, $service);
    }
}
