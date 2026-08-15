<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Odometer;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Vehicle;

class ServiceOrderController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $vehicleId = $this->parseId($request->input('vehicle_id'));

        if ($request->input('vehicle_id') !== null && $request->input('vehicle_id') !== '' && $vehicleId === null) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => ['vehicle_id' => ['شناسه خودرو باید عدد صحیح مثبت باشد.']],
            ], 422);
        }

        if ($vehicleId !== null) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
                return Response::json(['message' => 'خودرو پیدا نشد'], 404);
            }
        }

        $orders = ServiceOrder::forUser((int) $user->id, $vehicleId);

        return Response::json([
            'data' => array_map(static fn (ServiceOrder $o): array => $o->toArray(), $orders),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $order = $this->ownedOrderOrError($request, $id);
        if ($order instanceof Response) {
            return $order;
        }

        return Response::json(['data' => $order->toArray()]);
    }

    public function store(Request $request): Response
    {
        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        [$orderData, $items] = $payload;

        $userId = (int) $request->user()->id;
        $latest = Odometer::latestForVehicle((int) $orderData['vehicle_id']);
        if ($latest !== null && (int) $orderData['odometer'] < $latest->value) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => [
                    'odometer' => [
                        "مقدار کیلومترشمار نمی‌تواند کمتر از آخرین ثبت ({$latest->value}) باشد.",
                    ],
                ],
            ], 422);
        }

        $order = ServiceOrder::create([
            'user_id' => $userId,
            ...$orderData,
        ], $items);

        // Keep vehicle odometer history in sync with the service reading.
        Odometer::create([
            'user_id' => $userId,
            'vehicle_id' => (int) $orderData['vehicle_id'],
            'value' => (int) $orderData['odometer'],
        ]);

        return Response::json([
            'message' => 'سرویس ثبت شد',
            'data' => $order->toArray(),
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $order = $this->ownedOrderOrError($request, $id);
        if ($order instanceof Response) {
            return $order;
        }

        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        [$orderData, $items] = $payload;
        $order = $order->update($orderData, $items);

        return Response::json([
            'message' => 'سرویس به‌روز شد',
            'data' => $order->toArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $order = $this->ownedOrderOrError($request, $id);
        if ($order instanceof Response) {
            return $order;
        }

        $order->delete();

        return Response::json(['message' => 'سرویس حذف شد']);
    }

    private function ownedOrderOrError(Request $request, string $id): ServiceOrder|Response
    {
        $order = ServiceOrder::find((int) $id);

        if ($order === null) {
            return Response::json(['message' => 'سرویس پیدا نشد'], 404);
        }

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return Response::json(['message' => 'دسترسی مجاز نیست'], 403);
        }

        return $order;
    }

    /**
     * @return array{0: array{vehicle_id: int, service_date: string, odometer: int, notes: ?string}, 1: list<array{service_id: int, price: string, notes: ?string}>}|Response
     */
    private function validatedPayload(Request $request): array|Response
    {
        $user = $request->user();
        $vehicleId = $this->parseId($request->input('vehicle_id'));
        $serviceDateRaw = $request->input('service_date');
        $odometerRaw = $request->input('odometer');
        $notesRaw = $request->input('notes');
        $itemsRaw = $request->input('items');

        $errors = [];

        if ($vehicleId === null) {
            $errors['vehicle_id'][] = 'انتخاب خودرو الزامی است.';
        } else {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
                $errors['vehicle_id'][] = 'خودروی انتخاب‌شده معتبر نیست.';
            }
        }

        $serviceDate = null;
        if (! is_scalar($serviceDateRaw) || trim((string) $serviceDateRaw) === '') {
            $errors['service_date'][] = 'تاریخ سرویس الزامی است.';
        } else {
            $serviceDate = trim((string) $serviceDateRaw);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate) || ! $this->isValidDate($serviceDate)) {
                $errors['service_date'][] = 'تاریخ سرویس باید معتبر باشد (YYYY-MM-DD).';
            } else {
                $today = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tehran')))->format('Y-m-d');
                $minDate = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tehran')))
                    ->modify('-20 years')
                    ->format('Y-m-d');

                if ($serviceDate > $today) {
                    $errors['service_date'][] = 'تاریخ سرویس نمی‌تواند بعد از امروز باشد.';
                } elseif ($serviceDate < $minDate) {
                    $errors['service_date'][] = 'تاریخ سرویس نمی‌تواند بیشتر از ۲۰ سال قبل باشد.';
                }
            }
        }

        $odometer = $this->parseNonNegativeInt($odometerRaw);
        if ($odometer === null) {
            $errors['odometer'][] = 'کیلومترشمار الزامی است و باید عدد صحیح غیرمنفی باشد.';
        }

        $notes = null;
        if ($notesRaw !== null && $notesRaw !== '') {
            $notes = trim((string) $notesRaw);
        }

        $items = [];
        if (! is_array($itemsRaw) || $itemsRaw === []) {
            $errors['items'][] = 'حداقل یک آیتم سرویس الزامی است.';
        } else {
            foreach (array_values($itemsRaw) as $index => $item) {
                if (! is_array($item)) {
                    $errors['items'][] = "آیتم شماره {$index} نامعتبر است.";
                    continue;
                }

                $serviceId = $this->parseId($item['service_id'] ?? null);
                $price = $this->parseMoney($item['price'] ?? null);
                $itemNotes = null;

                if ($serviceId === null) {
                    $errors["items.{$index}.service_id"][] = 'انتخاب سرویس الزامی است.';
                } elseif (Service::find($serviceId) === null) {
                    $errors["items.{$index}.service_id"][] = 'سرویس انتخاب‌شده معتبر نیست.';
                }

                if ($price === null) {
                    $errors["items.{$index}.price"][] = 'قیمت باید مبلغ معتبر و غیرمنفی باشد.';
                }

                if (isset($item['notes']) && $item['notes'] !== null && $item['notes'] !== '') {
                    $itemNotes = trim((string) $item['notes']);
                }

                if ($serviceId !== null && $price !== null) {
                    $items[] = [
                        'service_id' => $serviceId,
                        'price' => $price,
                        'notes' => $itemNotes,
                    ];
                }
            }
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        return [
            [
                'vehicle_id' => (int) $vehicleId,
                'service_date' => (string) $serviceDate,
                'odometer' => (int) $odometer,
                'notes' => $notes,
            ],
            $items,
        ];
    }

    private function parseId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $id = (int) $value;

            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function parseNonNegativeInt(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && $value >= 0 && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }

    private function parseMoney(mixed $value): ?string
    {
        if (is_int($value) && $value >= 0) {
            return number_format($value, 2, '.', '');
        }

        if (is_float($value) && $value >= 0) {
            return number_format($value, 2, '.', '');
        }

        if (is_string($value) && preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1) {
            return number_format((float) $value, 2, '.', '');
        }

        return null;
    }

    private function isValidDate(string $date): bool
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
