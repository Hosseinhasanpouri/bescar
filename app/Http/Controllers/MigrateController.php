<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Connection;
use App\Database\Migrator;
use App\Http\Request;
use App\Http\Response;
use Throwable;

class MigrateController
{
    public function run(Request $request): Response
    {
        try {
            $migrator = new Migrator(Connection::get(), database_path('migrations'));
            $ran = $migrator->run();

            return Response::json([
                'message' => $ran === []
                    ? 'مهاجرت جدیدی برای اجرا وجود ندارد'
                    : 'مهاجرت‌ها با موفقیت اجرا شدند',
                'migrated' => $ran,
                'count' => count($ran),
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'message' => 'اجرای مهاجرت ناموفق بود',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): Response
    {
        try {
            $migrator = new Migrator(Connection::get(), database_path('migrations'));

            return Response::json([
                'data' => $migrator->status(),
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'message' => 'دریافت وضعیت مهاجرت ناموفق بود',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
