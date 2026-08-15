<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Document;
use App\Models\Vehicle;

class DocumentController
{
    public function index(Request $request): Response
    {
        $type = $request->input('type');
        $filterType = null;

        if ($type !== null && $type !== '') {
            $typeStr = is_scalar($type) ? (string) $type : '';
            if (! in_array($typeStr, [Document::TYPE_OWNER, Document::TYPE_VEHICLE], true)) {
                return Response::json([
                    'message' => 'اعتبارسنجی ناموفق بود',
                    'errors' => ['type' => ['نوع باید owner یا vehicle باشد.']],
                ], 422);
            }
            $filterType = $typeStr;
        }

        $documents = Document::forUser((int) $request->user()->id, $filterType);

        return Response::json([
            'data' => array_map(static fn (Document $doc): array => $doc->toArray(), $documents),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $document = $this->ownedDocumentOrError($request, $id);
        if ($document instanceof Response) {
            return $document;
        }

        return Response::json(['data' => $document->toArray()]);
    }

    public function store(Request $request): Response
    {
        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $document = Document::create([
            'user_id' => (int) $request->user()->id,
            ...$payload,
        ]);

        return Response::json([
            'message' => 'مدرک ایجاد شد',
            'data' => $document->toArray(),
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $document = $this->ownedDocumentOrError($request, $id);
        if ($document instanceof Response) {
            return $document;
        }

        $payload = $this->validatedPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $document = $document->update($payload);

        return Response::json([
            'message' => 'مدرک به‌روز شد',
            'data' => $document->toArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $document = $this->ownedDocumentOrError($request, $id);
        if ($document instanceof Response) {
            return $document;
        }

        $document->delete();

        return Response::json(['message' => 'مدرک حذف شد']);
    }

    private function ownedDocumentOrError(Request $request, string $id): Document|Response
    {
        $document = Document::find((int) $id);

        if ($document === null) {
            return Response::json(['message' => 'مدرک پیدا نشد'], 404);
        }

        if ((int) $document->user_id !== (int) $request->user()->id) {
            return Response::json(['message' => 'دسترسی مجاز نیست'], 403);
        }

        return $document;
    }

    /**
     * @return array{type: string, vehicle_id: ?int, title: string, expires_at: ?string, notes: ?string}|Response
     */
    private function validatedPayload(Request $request): array|Response
    {
        $typeRaw = $request->input('type', Document::TYPE_OWNER);
        $type = is_scalar($typeRaw) ? (string) $typeRaw : '';
        $title = trim((string) $request->input('title', ''));
        $vehicleIdRaw = $request->input('vehicle_id');
        $expiresAtRaw = $request->input('expires_at');
        $notesRaw = $request->input('notes');

        $errors = [];

        if (! in_array($type, [Document::TYPE_OWNER, Document::TYPE_VEHICLE], true)) {
            $errors['type'][] = 'نوع باید owner یا vehicle باشد.';
        }

        if ($title === '') {
            $errors['title'][] = 'عنوان الزامی است.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'][] = 'عنوان نباید بیشتر از ۲۵۵ کاراکتر باشد.';
        }

        $vehicleId = null;
        if ($type === Document::TYPE_VEHICLE) {
            $vehicleId = $this->parseId($vehicleIdRaw);
            if ($vehicleId === null) {
                $errors['vehicle_id'][] = 'برای مدارک خودرو، انتخاب خودرو الزامی است.';
            } else {
                $vehicle = Vehicle::find($vehicleId);
                if ($vehicle === null || (int) $vehicle->user_id !== (int) $request->user()->id) {
                    $errors['vehicle_id'][] = 'خودروی انتخاب‌شده معتبر نیست.';
                }
            }
        }

        $expiresAt = null;
        if ($expiresAtRaw !== null && $expiresAtRaw !== '') {
            $expiresAtStr = is_scalar($expiresAtRaw) ? trim((string) $expiresAtRaw) : '';
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAtStr) || ! $this->isValidDate($expiresAtStr)) {
                $errors['expires_at'][] = 'تاریخ انقضا باید معتبر باشد (YYYY-MM-DD).';
            } else {
                $expiresAt = $expiresAtStr;
            }
        }

        $notes = null;
        if ($notesRaw !== null && $notesRaw !== '') {
            if (! is_scalar($notesRaw)) {
                $errors['notes'][] = 'یادداشت باید متن باشد.';
            } else {
                $notes = trim((string) $notesRaw);
                if (mb_strlen($notes) > 5000) {
                    $errors['notes'][] = 'یادداشت نباید بیشتر از ۵۰۰۰ کاراکتر باشد.';
                }
            }
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        return [
            'type' => $type,
            'vehicle_id' => $type === Document::TYPE_VEHICLE ? $vehicleId : null,
            'title' => $title,
            'expires_at' => $expiresAt,
            'notes' => $notes === '' ? null : $notes,
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

    private function isValidDate(string $date): bool
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
