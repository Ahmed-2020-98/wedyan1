<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Painting;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $reservations = $request->user()->reservations()
            ->with(['items.painting'])
            ->latest()
            ->paginate(10);

        return ReservationResource::collection($reservations);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $reservation = $this->findOwned($request, $number);

        return response()->json([
            'data' => new ReservationResource($reservation->load(['items.painting', 'updates'])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'painting_id' => ['nullable', 'integer', 'exists:paintings,id'],
            'title' => ['required_without:painting_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'dimension_preset_id' => ['nullable', 'integer', 'exists:dimension_presets,id'],
            'width' => ['nullable', 'numeric', 'min:10', 'max:500'],
            'height' => ['nullable', 'numeric', 'min:10', 'max:500'],
            'unit' => ['nullable', 'string', 'in:cm,inch,m'],
            'preferred_colors' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ]);

        $painting = isset($data['painting_id'])
            ? Painting::published()->findOrFail($data['painting_id'])
            : null;

        $reservation = $this->reservations->create($request->user(), $painting, $data);

        return response()->json([
            'data' => new ReservationResource($reservation->load(['items', 'updates'])),
        ], 201);
    }

    /**
     * يبدأ دفع المستحق الآن (عربون أو متبقٍّ).
     *
     * المبلغ يُحسب في الخادم من حالة الحجز ولا يُقبل من الطلب إطلاقاً —
     * وإلا لأمكن للزبون أن يرسل ١ ريال ويعتبر لوحته مدفوعة.
     */
    public function pay(Request $request, string $number): JsonResponse
    {
        $reservation = $this->findOwned($request, $number);
        $amount = $reservation->amountDueNow();

        abort_if($amount <= 0, 422, 'لا يوجد مبلغ مستحق على هذا الحجز حالياً.');
        abort_unless($reservation->status->awaitsPayment(), 422, 'لا يمكن الدفع في الحالة الحالية للحجز.');

        $kind = (float) $reservation->paid_amount <= 0
            ? PaymentKind::Deposit
            : PaymentKind::Balance;

        $result = $this->payments->initiate(
            payable: $reservation,
            amount: $amount,
            kind: $kind,
            returnUrl: rtrim((string) config('platform.urls.web'), '/')
                . '/account/reservations/' . $reservation->reservation_number,
            description: $kind->label() . ' — حجز ' . $reservation->reservation_number,
        );

        /*
         | «بانتظار العربون» تعني أن الزبون أُرسل إلى البوابة فعلاً. تسجيلها
         | هنا يجعل لوحة الفنان تميّز من لم يبدأ الدفع بعد ممّن بدأ ولم يُكمل.
         */
        if ($reservation->status === \App\Enums\ReservationStatus::Accepted
            && $kind === PaymentKind::Deposit) {
            $reservation->update(['status' => \App\Enums\ReservationStatus::DepositPending]);
        }

        return response()->json([
            'data' => [
                'paymentNumber' => $result['payment']->payment_number,
                'redirectUrl' => $result['intent']->redirectUrl,
                'kind' => $kind->value,
                'kindLabel' => $kind->label(),
            ],
        ], 201);
    }

    private function findOwned(Request $request, string $number): Reservation
    {
        return $request->user()->reservations()
            ->where('reservation_number', $number)
            ->firstOrFail();
    }
}
