<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Api\V1\Manage\Concerns\ScopesToArtist;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ImageService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReservationController extends Controller
{
    use ScopesToArtist;

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ImageService $images,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->scopeForUser(Reservation::query(), $request->user())
            ->with(['items.painting', 'user:id,name,email,phone']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ReservationResource::collection($query->latest()->paginate(20));
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $reservation = $this->find($request, $number);

        return response()->json([
            'data' => new ReservationResource(
                $reservation->load(['items.painting', 'updates', 'user:id,name,email,phone'])
            ),
        ]);
    }

    /** قبول الطلب وتحديد السعر — الخطوة التي تفتح باب الدفع. */
    public function accept(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date', 'after:today'],
        ]);

        $reservation = $this->reservations->accept(
            $this->find($request, $number),
            (float) $data['price'],
            $data['note'] ?? null,
            $data['due_date'] ?? null,
        );

        return response()->json(['data' => new ReservationResource($reservation->load(['items', 'updates']))]);
    }

    public function reject(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $reservation = $this->reservations->reject($this->find($request, $number), $data['reason']);

        return response()->json(['data' => new ReservationResource($reservation->load(['items', 'updates']))]);
    }

    /** تحديث مرحلة بصورة — ما يجعل انتظار أسابيع محتملاً للزبون. */
    public function addUpdate(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'visible_to_customer' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);

        $reservation = $this->find($request, $number);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->images->store($request->file('image'), 'reservations')['path'];
        }

        $this->reservations->addUpdate(
            $reservation,
            $request->user(),
            $data['title'],
            $data['note'] ?? null,
            $imagePath,
            isset($data['progress_percentage']) ? (int) $data['progress_percentage'] : null,
            (bool) ($data['visible_to_customer'] ?? true),
        );

        return response()->json([
            'data' => new ReservationResource($reservation->fresh(['items', 'updates'])),
        ], 201);
    }

    public function complete(Request $request, string $number): JsonResponse
    {
        $reservation = $this->reservations->complete($this->find($request, $number));

        return response()->json(['data' => new ReservationResource($reservation->load(['items', 'updates']))]);
    }

    public function transition(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
        ]);

        $target = ReservationStatus::tryFrom($data['status']);
        abort_if($target === null, 422, 'حالة غير معروفة.');

        $reservation = $this->find($request, $number);

        if (isset($data['tracking_number'])) {
            $reservation->update(['tracking_number' => $data['tracking_number']]);
        }

        $reservation = $this->reservations->transition($reservation, $target);

        return response()->json(['data' => new ReservationResource($reservation->load(['items', 'updates']))]);
    }

    private function find(Request $request, string $number): Reservation
    {
        return $this->scopeForUser(Reservation::query(), $request->user())
            ->where('reservation_number', $number)
            ->firstOrFail();
    }
}
