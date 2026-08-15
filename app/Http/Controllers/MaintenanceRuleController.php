<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\MaintenanceRule;
use App\Models\Odometer;
use App\Models\Service;
use App\Models\Vehicle;

class MaintenanceRuleController
{
    public function index(Request $request): Response
    {
        $vehicleId = $this->parseId($request->input('vehicle_id'));

        if ($request->input('vehicle_id') !== null && $request->input('vehicle_id') !== '' && $vehicleId === null) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => ['vehicle_id' => ['شناسه خودرو باید عدد صحیح مثبت باشد.']],
            ], 422);
        }

        if ($vehicleId !== null) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle === null || (int) $vehicle->user_id !== (int) $request->user()->id) {
                return Response::json(['message' => 'خودرو پیدا نشد'], 404);
            }
        }

        $rules = MaintenanceRule::forUser((int) $request->user()->id, $vehicleId);

        return Response::json([
            'data' => array_map(static fn (MaintenanceRule $rule): array => $rule->toArray(), $rules),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $rule = $this->ownedRuleOrError($request, $id);
        if ($rule instanceof Response) {
            return $rule;
        }

        return Response::json(['data' => $rule->toArray()]);
    }

    public function store(Request $request): Response
    {
        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $existing = MaintenanceRule::findForVehicleService(
            (int) $payload['vehicle_id'],
            (int) $payload['service_id']
        );
        if ($existing !== null) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => [
                    'service_id' => ['برای این خودرو و سرویس قبلاً قانون نگهداری ثبت شده است.'],
                ],
            ], 422);
        }

        $rule = MaintenanceRule::create([
            'user_id' => (int) $request->user()->id,
            ...$payload,
        ]);

        return Response::json([
            'message' => 'قانون نگهداری ایجاد شد',
            'data' => $rule->toArray(),
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $rule = $this->ownedRuleOrError($request, $id);
        if ($rule instanceof Response) {
            return $rule;
        }

        $payload = $this->validatedPayload($request, $rule);
        if ($payload instanceof Response) {
            return $payload;
        }

        if ((int) $payload['service_id'] !== (int) $rule->service_id) {
            $existing = MaintenanceRule::findForVehicleService(
                (int) $rule->vehicle_id,
                (int) $payload['service_id']
            );
            if ($existing !== null && (int) $existing->id !== (int) $rule->id) {
                return Response::json([
                    'message' => 'اعتبارسنجی ناموفق بود',
                    'errors' => [
                        'service_id' => ['برای این خودرو و سرویس قبلاً قانون نگهداری ثبت شده است.'],
                    ],
                ], 422);
            }
        }

        // Vehicle is fixed on update; only service/intervals change.
        $rule = $rule->update([
            'service_id' => $payload['service_id'],
            'interval_km' => $payload['interval_km'],
            'interval_months' => $payload['interval_months'],
        ]);

        return Response::json([
            'message' => 'قانون نگهداری به‌روز شد',
            'data' => $rule->toArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $rule = $this->ownedRuleOrError($request, $id);
        if ($rule instanceof Response) {
            return $rule;
        }

        $rule->delete();

        return Response::json(['message' => 'قانون نگهداری حذف شد']);
    }

    /** Status for all maintenance rules of a vehicle (remaining / next due). */
    public function vehicleStatus(Request $request, string $vehicleId): Response
    {
        $id = $this->parseId($vehicleId);
        if ($id === null) {
            return Response::json(['message' => 'خودرو پیدا نشد'], 404);
        }

        $vehicle = Vehicle::find($id);
        if ($vehicle === null || (int) $vehicle->user_id !== (int) $request->user()->id) {
            return Response::json(['message' => 'خودرو پیدا نشد'], 404);
        }

        $latest = Odometer::latestForVehicle($id);
        $currentOdometer = $latest?->value;
        $rules = MaintenanceRule::effectiveForVehicle((int) $request->user()->id, $id);

        $items = array_map(
            static fn (MaintenanceRule $rule): array => $rule->status($currentOdometer),
            $rules
        );

        return Response::json([
            'data' => [
                'vehicle_id' => $id,
                'current_odometer' => $currentOdometer,
                'items' => $items,
            ],
        ]);
    }

    /** Overview of maintenance status across all of the user's vehicles. */
    public function overview(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $rules = MaintenanceRule::effectiveForUser($userId);
        $items = [];

        $odometerCache = [];
        $vehicleCache = [];

        foreach ($rules as $rule) {
            $vehicleId = (int) $rule->vehicle_id;

            if (! array_key_exists($vehicleId, $odometerCache)) {
                $odometerCache[$vehicleId] = Odometer::latestForVehicle($vehicleId)?->value;
            }
            if (! array_key_exists($vehicleId, $vehicleCache)) {
                $vehicleCache[$vehicleId] = Vehicle::find($vehicleId);
            }

            $status = $rule->status($odometerCache[$vehicleId]);
            $vehicle = $vehicleCache[$vehicleId];
            $status['vehicle'] = $vehicle?->toArray();
            $items[] = $status;
        }

        usort($items, static function (array $a, array $b): int {
            $aDue = ! empty($a['is_due']) || ! empty($a['never_serviced']) ? 0 : 1;
            $bDue = ! empty($b['is_due']) || ! empty($b['never_serviced']) ? 0 : 1;
            if ($aDue !== $bDue) {
                return $aDue <=> $bDue;
            }

            $aProgress = $a['progress'] ?? 101;
            $bProgress = $b['progress'] ?? 101;

            return $aProgress <=> $bProgress;
        });

        return Response::json([
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    private function ownedRuleOrError(Request $request, string $id): MaintenanceRule|Response
    {
        $rule = MaintenanceRule::find((int) $id);

        if ($rule === null) {
            return Response::json(['message' => 'قانون نگهداری پیدا نشد'], 404);
        }

        if ((int) $rule->user_id !== (int) $request->user()->id) {
            return Response::json(['message' => 'دسترسی مجاز نیست'], 403);
        }

        return $rule;
    }

    /**
     * @return array{vehicle_id: int, service_id: int, interval_km: ?int, interval_months: ?int}|Response
     */
    private function validatedPayload(Request $request, ?MaintenanceRule $existing = null): array|Response
    {
        $user = $request->user();
        $input = $request->all();

        $vehicleId = $this->parseId(
            array_key_exists('vehicle_id', $input) ? $input['vehicle_id'] : $existing?->vehicle_id
        );
        $serviceId = $this->parseId(
            array_key_exists('service_id', $input) ? $input['service_id'] : $existing?->service_id
        );

        $errors = [];

        if ($vehicleId === null) {
            $errors['vehicle_id'][] = 'انتخاب خودرو الزامی است.';
        } else {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle === null || (int) $vehicle->user_id !== (int) $user->id) {
                $errors['vehicle_id'][] = 'خودروی انتخاب‌شده معتبر نیست.';
            }
        }

        if ($serviceId === null) {
            $errors['service_id'][] = 'انتخاب سرویس الزامی است.';
        } elseif (Service::find($serviceId) === null) {
            $errors['service_id'][] = 'سرویس انتخاب‌شده معتبر نیست.';
        }

        $intervalKm = $existing?->interval_km;
        if (array_key_exists('interval_km', $input)) {
            if ($input['interval_km'] === null || $input['interval_km'] === '') {
                $intervalKm = null;
            } else {
                $intervalKm = $this->parsePositiveInt($input['interval_km']);
                if ($intervalKm === null) {
                    $errors['interval_km'][] = 'فاصله کیلومتر باید عدد صحیح مثبت باشد.';
                }
            }
        }

        $intervalMonths = $existing?->interval_months;
        if (array_key_exists('interval_months', $input)) {
            if ($input['interval_months'] === null || $input['interval_months'] === '') {
                $intervalMonths = null;
            } else {
                $intervalMonths = $this->parsePositiveInt($input['interval_months']);
                if ($intervalMonths === null) {
                    $errors['interval_months'][] = 'فاصله زمانی باید عدد صحیح مثبت (ماه) باشد.';
                }
            }
        }

        if ($intervalKm === null && $intervalMonths === null && ! isset($errors['interval_km']) && ! isset($errors['interval_months'])) {
            $errors['interval_km'][] = 'حداقل یکی از فاصله کیلومتر یا فاصله زمانی (ماه) الزامی است.';
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        return [
            'vehicle_id' => (int) $vehicleId,
            'service_id' => (int) $serviceId,
            'interval_km' => $intervalKm,
            'interval_months' => $intervalMonths,
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

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $n = (int) $value;

            return $n > 0 ? $n : null;
        }

        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }
}
