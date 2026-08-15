<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Vehicle;
use App\Models\VehicleModel;

class VehicleModelController
{
    public function index(Request $request): Response
    {
        $manufactorId = $request->input('manufactor_id');
        $vehicleType = $request->input('vehicle_type');
        $filterId = null;
        $filterType = null;

        if ($manufactorId !== null && $manufactorId !== '') {
            if (is_int($manufactorId) && $manufactorId > 0) {
                $filterId = $manufactorId;
            } elseif (is_string($manufactorId) && preg_match('/^\d+$/', $manufactorId) === 1) {
                $filterId = (int) $manufactorId;
            } else {
                return Response::json([
                    'message' => 'اعتبارسنجی ناموفق بود',
                    'errors' => ['manufactor_id' => ['شناسه سازنده باید عدد صحیح مثبت باشد.']],
                ], 422);
            }
        }

        if ($vehicleType !== null && $vehicleType !== '') {
            if (! in_array($vehicleType, Vehicle::VALID_TYPES, true)) {
                return Response::json([
                    'message' => 'اعتبارسنجی ناموفق بود',
                    'errors' => ['vehicle_type' => ['نوع وسیله نقلیه معتبر نیست.']],
                ], 422);
            }
            $filterType = $vehicleType;
        }

        $models = VehicleModel::all($filterId, $filterType);

        return Response::json([
            'data' => array_map(static fn (VehicleModel $m): array => $m->toArray(), $models),
        ]);
    }
}
