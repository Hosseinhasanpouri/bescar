<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MaintenanceRuleController;
use App\Http\Controllers\ManufactorController;
use App\Http\Controllers\MigrateController;
use App\Http\Controllers\OdometerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleModelController;
use App\Routing\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'api', 'middleware' => ['cors', 'json']], function () {
    // Public auth routes — OTP login (auto-creates user on first verify)
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/otp/request', [AuthController::class, 'requestOtp'])->name('auth.otp.request');
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
    });

    // Admin migrate — username/password (Basic Auth or JSON body), not JWT
    Route::group(['middleware' => ['basic']], function () {
        Route::post('/migrate', [MigrateController::class, 'run'])->name('migrate.run');
        Route::get('/migrate/status', [MigrateController::class, 'status'])->name('migrate.status');
    });

    // Protected routes
    Route::group(['middleware' => ['auth']], function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('/manufactors', [ManufactorController::class, 'index'])->name('manufactors.index');
        Route::get('/vehicle-models', [VehicleModelController::class, 'index'])->name('vehicle-models.index');

        Route::get('/plate-alphabets', [VehicleController::class, 'plateAlphabets'])->name('plate-alphabets');

        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->name('vehicles.show');
        Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::patch('/vehicles/{id}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');

        Route::get('/vehicles/{id}/maintenance-status', [MaintenanceRuleController::class, 'vehicleStatus'])
            ->name('vehicles.maintenance-status');
        Route::get('/maintenance-status', [MaintenanceRuleController::class, 'overview'])
            ->name('maintenance-status.overview');

        Route::get('/maintenance-rules', [MaintenanceRuleController::class, 'index'])->name('maintenance-rules.index');
        Route::post('/maintenance-rules', [MaintenanceRuleController::class, 'store'])->name('maintenance-rules.store');
        Route::get('/maintenance-rules/{id}', [MaintenanceRuleController::class, 'show'])->name('maintenance-rules.show');
        Route::put('/maintenance-rules/{id}', [MaintenanceRuleController::class, 'update'])->name('maintenance-rules.update');
        Route::patch('/maintenance-rules/{id}', [MaintenanceRuleController::class, 'update']);
        Route::delete('/maintenance-rules/{id}', [MaintenanceRuleController::class, 'destroy'])->name('maintenance-rules.destroy');

        Route::get('/odometer', [OdometerController::class, 'index'])->name('odometer.index');
        Route::get('/odometer/latest', [OdometerController::class, 'latest'])->name('odometer.latest');
        Route::post('/odometer', [OdometerController::class, 'store'])->name('odometer.store');

        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
        Route::put('/documents/{id}', [DocumentController::class, 'update'])->name('documents.update');
        Route::patch('/documents/{id}', [DocumentController::class, 'update']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{id}', [ServiceController::class, 'show'])->name('services.show');
        Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
        Route::patch('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/service-orders', [ServiceOrderController::class, 'index'])->name('service-orders.index');
        Route::post('/service-orders', [ServiceOrderController::class, 'store'])->name('service-orders.store');
        Route::get('/service-orders/{id}', [ServiceOrderController::class, 'show'])->name('service-orders.show');
        Route::put('/service-orders/{id}', [ServiceOrderController::class, 'update'])->name('service-orders.update');
        Route::patch('/service-orders/{id}', [ServiceOrderController::class, 'update']);
        Route::delete('/service-orders/{id}', [ServiceOrderController::class, 'destroy'])->name('service-orders.destroy');
    });
});
