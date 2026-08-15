<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Odometer;
use App\Models\Vehicle;

class OdometerController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $vehicleId = $this->parseId($request->input('vehicle_id'));

        if ($vehicleId !== null) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
                return Response::json(['message' => 'خودرو پیدا نشد'], 404);
            }
        }

        $rows = Odometer::forUser((int) $user->id, $vehicleId);

        return Response::json([
            'data' => array_map(static fn (Odometer $row): array => $row->toArray(), $rows),
        ]);
    }

    public function latest(Request $request): Response
    {
        $user = $request->user();
        $vehicleId = $this->parseId($request->input('vehicle_id'));

        if ($vehicleId === null) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => ['vehicle_id' => ['انتخاب خودرو الزامی است.']],
            ], 422);
        }

        $vehicle = Vehicle::find($vehicleId);
        if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
            return Response::json(['message' => 'خودرو پیدا نشد'], 404);
        }

        $latest = Odometer::latestForVehicle($vehicleId);

        return Response::json([
            'data' => $latest?->toArray(),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $request->user();
        $vehicleId = $this->parseId($request->input('vehicle_id'));
        $value = $this->parseValue($request->input('value'));

        $errors = [];

        if ($vehicleId === null) {
            $errors['vehicle_id'][] = 'انتخاب خودرو الزامی است.';
        }

        if ($value === null) {
            $errors['value'][] = 'مقدار کیلومترشمار الزامی است و باید عدد صحیح غیرمنفی باشد.';
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        $vehicle = Vehicle::find((int) $vehicleId);
        if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => ['vehicle_id' => ['خودروی انتخاب‌شده معتبر نیست.']],
            ], 422);
        }

        $latest = Odometer::latestForVehicle((int) $vehicleId);
        if ($latest !== null && (int) $value < $latest->value) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => [
                    'value' => [
                        "مقدار کیلومترشمار نمی‌تواند کمتر از آخرین ثبت ({$latest->value}) باشد.",
                    ],
                ],
            ], 422);
        }

        $row = Odometer::create([
            'user_id' => (int) $user->id,
            'vehicle_id' => (int) $vehicleId,
            'value' => (int) $value,
        ]);

        return Response::json([
            'message' => 'کارکرد ذخیره شد',
            'data' => $row->toArray(),
        ], 201);
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

    private function parseValue(mixed $value): ?int
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
}
