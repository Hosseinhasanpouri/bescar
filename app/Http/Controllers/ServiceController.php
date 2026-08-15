<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Service;

class ServiceController
{
    public function index(Request $request): Response
    {
        $activeOnly = ! filter_var($request->input('all', false), FILTER_VALIDATE_BOOLEAN);
        $services = Service::all($activeOnly);

        return Response::json([
            'data' => array_map(static fn (Service $s): array => $s->toArray(), $services),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $service = Service::find((int) $id);
        if ($service === null) {
            return Response::json(['message' => 'سرویس پیدا نشد'], 404);
        }

        return Response::json(['data' => $service->toArray()]);
    }

    public function store(Request $request): Response
    {
        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $service = Service::create($payload);

        return Response::json([
            'message' => 'سرویس ایجاد شد',
            'data' => $service->toArray(),
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $service = Service::find((int) $id);
        if ($service === null) {
            return Response::json(['message' => 'سرویس پیدا نشد'], 404);
        }

        $payload = $this->validatedPayload($request, (int) $service->id);
        if ($payload instanceof Response) {
            return $payload;
        }

        $service = $service->update($payload);

        return Response::json([
            'message' => 'سرویس به‌روز شد',
            'data' => $service->toArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $service = Service::find((int) $id);
        if ($service === null) {
            return Response::json(['message' => 'سرویس پیدا نشد'], 404);
        }

        try {
            $service->delete();
        } catch (\Throwable) {
            return Response::json([
                'message' => 'این سرویس به‌خاطر استفاده در سفارش‌ها قابل حذف نیست.',
            ], 409);
        }

        return Response::json(['message' => 'سرویس حذف شد']);
    }

    /**
     * @return array{
     *   name: string,
     *   slug: string,
     *   description: ?string,
     *   is_active: bool,
     *   default_interval_km: ?int,
     *   default_interval_months: ?int
     * }|Response
     */
    private function validatedPayload(Request $request, ?int $ignoreId = null): array|Response
    {
        $name = trim((string) $request->input('name', ''));
        $slugRaw = $request->input('slug');
        $descriptionRaw = $request->input('description');
        $isActiveRaw = $request->input('is_active', true);
        $input = $request->all();

        $errors = [];

        if ($name === '') {
            $errors['name'][] = 'نام الزامی است.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'][] = 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد.';
        }

        $slug = null;
        if ($slugRaw !== null && $slugRaw !== '') {
            $slug = Service::slugify((string) $slugRaw);
            if ($slug === '' || mb_strlen($slug) > 255) {
                $errors['slug'][] = 'اسلاگ نامعتبر است.';
            } else {
                $existing = Service::findBySlug($slug);
                if ($existing !== null && $existing->id !== $ignoreId) {
                    $errors['slug'][] = 'این اسلاگ قبلاً استفاده شده است.';
                }
            }
        } else {
            $slug = Service::uniqueSlug($name !== '' ? $name : 'service', $ignoreId);
        }

        $description = null;
        if ($descriptionRaw !== null && $descriptionRaw !== '') {
            $description = trim((string) $descriptionRaw);
        }

        $isActive = filter_var($isActiveRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            $isActive = true;
        }

        $defaultIntervalKm = null;
        if (array_key_exists('default_interval_km', $input)) {
            if ($input['default_interval_km'] === null || $input['default_interval_km'] === '') {
                $defaultIntervalKm = null;
            } else {
                $defaultIntervalKm = $this->parsePositiveInt($input['default_interval_km']);
                if ($defaultIntervalKm === null) {
                    $errors['default_interval_km'][] = 'فاصله پیش‌فرض کیلومتر باید عدد صحیح مثبت باشد.';
                }
            }
        }

        $defaultIntervalMonths = null;
        if (array_key_exists('default_interval_months', $input)) {
            if ($input['default_interval_months'] === null || $input['default_interval_months'] === '') {
                $defaultIntervalMonths = null;
            } else {
                $defaultIntervalMonths = $this->parsePositiveInt($input['default_interval_months']);
                if ($defaultIntervalMonths === null) {
                    $errors['default_interval_months'][] = 'فاصله پیش‌فرض زمانی باید عدد صحیح مثبت (ماه) باشد.';
                }
            }
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        $payload = [
            'name' => $name,
            'slug' => (string) $slug,
            'description' => $description,
            'is_active' => $isActive,
        ];

        if (array_key_exists('default_interval_km', $input)) {
            $payload['default_interval_km'] = $defaultIntervalKm;
        }
        if (array_key_exists('default_interval_months', $input)) {
            $payload['default_interval_months'] = $defaultIntervalMonths;
        }

        return $payload;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }
        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }
}
