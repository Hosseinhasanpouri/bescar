<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Vehicle;
use App\Models\VehicleModel;

class VehicleController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $vehicles = Vehicle::forUser((int) $user->id);

        return Response::json([
            'data' => array_map(static fn (Vehicle $v): array => $v->toArray(), $vehicles),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $vehicle = $this->ownedVehicleOrError($request, $id);
        if ($vehicle instanceof Response) {
            return $vehicle;
        }

        return Response::json(['data' => $vehicle->toArray()]);
    }

    public function store(Request $request): Response
    {
        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $vehicle = Vehicle::create([
            'user_id' => (int) $request->user()->id,
            ...$payload,
        ]);

        return Response::json([
            'message' => 'خودرو ایجاد شد',
            'data' => $vehicle->toArray(),
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $vehicle = $this->ownedVehicleOrError($request, $id);
        if ($vehicle instanceof Response) {
            return $vehicle;
        }

        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        // Vehicle type and model cannot be changed after creation.
        unset($payload['vehicle_type']);
        $payload['vehicle_model_id'] = (int) $vehicle->vehicle_model_id;

        $vehicle = $vehicle->update($payload);

        return Response::json([
            'message' => 'خودرو به‌روز شد',
            'data' => $vehicle->toArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $vehicle = $this->ownedVehicleOrError($request, $id);
        if ($vehicle instanceof Response) {
            return $vehicle;
        }

        $vehicle->delete();

        return Response::json(['message' => 'خودرو حذف شد']);
    }

    /** Return plate alphabets for frontend. */
    public function plateAlphabets(): Response
    {
        return Response::json(['data' => Vehicle::PLATE_ALPHABETS]);
    }

    private function ownedVehicleOrError(Request $request, string $id): Vehicle|Response
    {
        $vehicle = Vehicle::find((int) $id);

        if ($vehicle === null) {
            return Response::json(['message' => 'خودرو پیدا نشد'], 404);
        }

        if ((int) $vehicle->user_id !== (int) $request->user()->id) {
            return Response::json(['message' => 'دسترسی مجاز نیست'], 403);
        }

        return $vehicle;
    }

    private function validatedPayload(Request $request): array|Response
    {
        $vehicleModelId = $this->parseId($request->input('vehicle_model_id'));
        $nameRaw = $request->input('name');
        $yearRaw = $request->input('year');
        $vinRaw = $request->input('vin');
        $vehicleTypeRaw = $request->input('vehicle_type');
        $plateTypeRaw = $request->input('plate_type');
        $plateRaw = $request->input('plate');

        $errors = [];

        if ($vehicleModelId === null) {
            $errors['vehicle_model_id'][] = 'انتخاب مدل خودرو الزامی است.';
        } elseif (VehicleModel::find($vehicleModelId) === null) {
            $errors['vehicle_model_id'][] = 'مدل خودروی انتخاب‌شده معتبر نیست.';
        }

        $name = null;
        if ($nameRaw !== null && $nameRaw !== '') {
            if (! is_scalar($nameRaw)) {
                $errors['name'][] = 'نام باید متن باشد.';
            } else {
                $name = trim((string) $nameRaw);
                if ($name === '') {
                    $name = null;
                } elseif (mb_strlen($name) > 255) {
                    $errors['name'][] = 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد.';
                }
            }
        }

        $year = null;
        if ($yearRaw !== null && $yearRaw !== '') {
            $parsedYear = $this->parseYear($yearRaw);
            $currentYear = (int) date('Y');
            if ($parsedYear === null) {
                $errors['year'][] = 'سال باید عدد معتبر باشد.';
            } elseif ($parsedYear < 1900 || $parsedYear > $currentYear + 1) {
                $errors['year'][] = 'سال باید بین ۱۹۰۰ تا ' . ($currentYear + 1) . ' باشد.';
            } else {
                $year = $parsedYear;
            }
        }

        $vin = null;
        if ($vinRaw !== null && $vinRaw !== '') {
            if (! is_scalar($vinRaw)) {
                $errors['vin'][] = 'شماره VIN باید متن باشد.';
            } else {
                $vin = strtoupper(trim((string) $vinRaw));
                if ($vin === '') {
                    $vin = null;
                } elseif (! preg_match('/^[A-HJ-NPR-Z0-9]{1,17}$/', $vin)) {
                    $errors['vin'][] = 'شماره VIN فقط می‌تواند شامل حروف و اعداد باشد (حداکثر ۱۷ کاراکتر، بدون I و O و Q).';
                }
            }
        }

        $vehicleType = null;
        if ($vehicleTypeRaw !== null && $vehicleTypeRaw !== '') {
            if (! in_array($vehicleTypeRaw, Vehicle::VALID_TYPES, true)) {
                $errors['vehicle_type'][] = 'نوع وسیله نقلیه معتبر نیست.';
            } else {
                $vehicleType = $vehicleTypeRaw;
            }
        }

        $plateType = null;
        $plate = null;

        if ($plateTypeRaw !== null && $plateTypeRaw !== '') {
            if (! in_array($plateTypeRaw, Vehicle::VALID_PLATE_TYPES, true)) {
                $errors['plate_type'][] = 'نوع پلاک معتبر نیست.';
            } else {
                $plateType = $plateTypeRaw;
            }

            if ($vehicleType === Vehicle::TYPE_MOTORCYCLE && $plateType !== null && $plateType !== Vehicle::PLATE_MOTORCYCLE) {
                $errors['plate_type'][] = 'برای موتورسیکلت فقط پلاک موتورسیکلت مجاز است.';
            } elseif (in_array($vehicleType, [Vehicle::TYPE_CAR, Vehicle::TYPE_TRUCK], true) && $plateType === Vehicle::PLATE_MOTORCYCLE) {
                $errors['plate_type'][] = 'برای خودرو/کامیون پلاک موتورسیکلت مجاز نیست.';
            }
        }

        if ($plateRaw !== null && $plateRaw !== '') {
            if (! is_scalar($plateRaw)) {
                $errors['plate'][] = 'پلاک باید متن باشد.';
            } else {
                $plate = trim((string) $plateRaw);
                if ($plate === '') {
                    $plate = null;
                } elseif ($plateType === null) {
                    $errors['plate_type'][] = 'برای ثبت پلاک، نوع پلاک الزامی است.';
                } elseif (! Vehicle::validatePlate($plateType, $plate)) {
                    $errors['plate'][] = 'فرمت پلاک با نوع پلاک انتخاب‌شده مطابقت ندارد.';
                }
            }
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        return [
            'vehicle_model_id' => (int) $vehicleModelId,
            'name' => $name,
            'year' => $year,
            'vin' => $vin,
            'vehicle_type' => $vehicleType,
            'plate_type' => $plateType,
            'plate' => $plate,
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

    private function parseYear(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d{4}$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }
}
