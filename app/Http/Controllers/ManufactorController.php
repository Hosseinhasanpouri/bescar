<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Manufactor;
use App\Models\Vehicle;

class ManufactorController
{
    public function index(Request $request): Response
    {
        $vehicleType = $request->input('vehicle_type');
        $filterType = null;

        if ($vehicleType !== null && $vehicleType !== '') {
            if (! in_array($vehicleType, Vehicle::VALID_TYPES, true)) {
                return Response::json([
                    'message' => 'اعتبارسنجی ناموفق بود',
                    'errors' => ['vehicle_type' => ['نوع وسیله نقلیه معتبر نیست.']],
                ], 422);
            }
            $filterType = $vehicleType;
        }

        $items = Manufactor::all($filterType);

        return Response::json([
            'data' => array_map(static fn (Manufactor $m): array => $m->toArray(), $items),
        ]);
    }
}
