<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentKind;
use App\Enums\ReservationStatus;
use App\Models\Painting;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationUpdate;
use App\Models\User;
use App\Notifications\ReservationStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReservationService
{
    /**
     * ينشئ طلب حجز.
     *
     * لاحظ أن السعر يبقى null: اللوحة المخصّصة تُسعَّر بعد أن يفهم الفنان
     * المطلوب، لا قبله. ولذلك لا يوجد دفع في هذه الخطوة إطلاقاً.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, ?Painting $painting, array $data): Reservation
    {
        if ($painting !== null && ! $painting->isReservable()) {
            throw ValidationException::withMessages([
                'painting' => ['هذه اللوحة لا تقبل الحجز.'],
            ]);
        }

        return DB::transaction(function () use ($user, $painting, $data): Reservation {
            $artistId = $painting?->artist_id ?? User::role(\App\Enums\Role::Artist->value)->value('id');

            $reservation = Reservation::create([
                'reservation_number' => $this->generateNumber(),
                'user_id' => $user->id,
                'artist_id' => $artistId,
                'status' => ReservationStatus::Pending,
                'deposit_percentage' => $painting?->deposit_percentage
                    ?? (int) config('platform.reservation.deposit_percentage', 50),
                'currency' => config('platform.currency'),
                'customer_note' => $data['note'] ?? null,
                'shipping_name' => $data['recipient_name'] ?? null,
                'shipping_phone' => $data['phone'] ?? null,
                'shipping_country' => $data['country'] ?? 'SA',
                'shipping_city' => $data['city'] ?? null,
                'shipping_district' => $data['district'] ?? null,
                'shipping_street' => $data['street'] ?? null,
                'shipping_postal_code' => $data['postal_code'] ?? null,
            ]);

            $reservation->items()->create([
                'painting_id' => $painting?->id,
                'dimension_preset_id' => $data['dimension_preset_id'] ?? null,
                'title' => $data['title'] ?? $painting?->title ?? 'لوحة مخصّصة',
                'description' => $data['description'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'unit' => $data['unit'] ?? 'cm',
                'preferred_colors' => $data['preferred_colors'] ?? null,
            ]);

            $this->log($reservation, null, ReservationStatus::Pending, 'وصل طلب الحجز', 'سيراجع الفنان طلبك ويردّ عليك قريباً.');

            return $reservation->load('items');
        });
    }

    /** الفنان يقبل الطلب ويحدّد السعر والمدة. */
    public function accept(Reservation $reservation, float $price, ?string $note, ?string $dueDate): Reservation
    {
        $this->guardTransition($reservation, ReservationStatus::Accepted);

        $deposit = round($price * ($reservation->deposit_percentage / 100), 2);

        $reservation->update([
            'status' => ReservationStatus::Accepted,
            'price' => $price,
            'deposit_amount' => $deposit,
            'artist_note' => $note,
            'due_date' => $dueDate,
            'reviewed_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->log(
            $reservation,
            ReservationStatus::Pending,
            ReservationStatus::Accepted,
            'قُبل طلبك',
            'حدّد الفنان السعر. ادفع العربون لبدء التنفيذ.',
        );

        $reservation->user->notify(new ReservationStatusChangedNotification($reservation));

        return $reservation->refresh();
    }

    public function reject(Reservation $reservation, string $reason): Reservation
    {
        $this->guardTransition($reservation, ReservationStatus::Rejected);

        $reservation->update([
            'status' => ReservationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);

        $this->log($reservation, null, ReservationStatus::Rejected, 'اعتذر الفنان عن الطلب', $reason);
        $reservation->user->notify(new ReservationStatusChangedNotification($reservation));

        return $reservation->refresh();
    }

    /**
     * يسجّل دفعة موثَّقة على الحجز.
     *
     * العربون يبدأ الإنتاج، والدفعة الأخيرة تُجهّز اللوحة للتسليم. تُستدعى
     * من PaymentService بعد التحقق من التوقيع فقط.
     */
    public function recordPayment(Reservation $reservation, Payment $payment): void
    {
        $paid = round((float) $reservation->paid_amount + (float) $payment->amount, 2);

        $reservation->update(['paid_amount' => $paid]);
        $reservation->refresh();

        $from = $reservation->status;

        /*
         | الدفعة الأولى تبدأ الإنتاج، والأخيرة (بعد اكتمال اللوحة) تُجهّزها
         | للتسليم. ما عدا ذلك تُسجَّل الدفعة ولا تُحرّك الحالة — دفعة تصل
         | في منتصف الإنتاج يجب ألّا تقفز باللوحة إلى «جاهزة».
         */
        if (in_array($from, [ReservationStatus::Accepted, ReservationStatus::DepositPending], true)) {
            $reservation->update([
                'status' => ReservationStatus::InProduction,
                'production_started_at' => now(),
            ]);

            $this->log(
                $reservation,
                $from,
                ReservationStatus::InProduction,
                $payment->kind === PaymentKind::Deposit ? 'وصل العربون — بدأ التنفيذ' : 'وصل المبلغ — بدأ التنفيذ',
                'سيوافيك الفنان بتحديثات مصوّرة لمراحل الرسم.',
            );
        } elseif ($from === ReservationStatus::Completed && $reservation->isFullyPaid()) {
            $reservation->update(['status' => ReservationStatus::ReadyForDelivery]);

            $this->log(
                $reservation,
                $from,
                ReservationStatus::ReadyForDelivery,
                'اكتمل السداد — جاهزة للتسليم',
                'سنبدأ إجراءات الشحن.',
            );
        }

        $reservation->user->notify(new ReservationStatusChangedNotification($reservation->refresh()));
    }

    /** تحديث مرحلة من الفنان، بصورة اختيارية ونسبة إنجاز. */
    public function addUpdate(
        Reservation $reservation,
        User $author,
        string $title,
        ?string $note,
        ?string $imagePath,
        ?int $progress,
        bool $visibleToCustomer = true,
    ): ReservationUpdate {
        if ($progress !== null) {
            $reservation->update(['progress_percentage' => max(0, min(100, $progress))]);
        }

        $update = $reservation->updates()->create([
            'created_by' => $author->id,
            'title' => $title,
            'note' => $note,
            'image_path' => $imagePath,
            'progress_percentage' => $progress,
            'is_visible_to_customer' => $visibleToCustomer,
        ]);

        if ($visibleToCustomer) {
            $reservation->user->notify(new ReservationStatusChangedNotification($reservation));
        }

        return $update;
    }

    /** الفنان يعلن اكتمال اللوحة. */
    public function complete(Reservation $reservation): Reservation
    {
        $this->guardTransition($reservation, ReservationStatus::Completed);

        $reservation->update([
            'status' => ReservationStatus::Completed,
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        $message = $reservation->isFullyPaid()
            ? 'اكتملت لوحتك وسنبدأ الشحن.'
            : 'اكتملت لوحتك. يتبقّى سداد المبلغ المتبقّي قبل الشحن.';

        $this->log($reservation, ReservationStatus::InProduction, ReservationStatus::Completed, 'اكتملت اللوحة', $message);
        $reservation->user->notify(new ReservationStatusChangedNotification($reservation));

        return $reservation->refresh();
    }

    public function transition(Reservation $reservation, ReservationStatus $target): Reservation
    {
        $this->guardTransition($reservation, $target);

        $from = $reservation->status;
        $reservation->update(['status' => $target]);

        $this->log($reservation, $from, $target, 'تحديث الحالة: ' . $target->label(), null);
        $reservation->user->notify(new ReservationStatusChangedNotification($reservation));

        return $reservation->refresh();
    }

    private function guardTransition(Reservation $reservation, ReservationStatus $target): void
    {
        if (! $reservation->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => [sprintf(
                    'لا يمكن الانتقال من «%s» إلى «%s».',
                    $reservation->status->label(),
                    $target->label(),
                )],
            ]);
        }
    }

    private function log(
        Reservation $reservation,
        ?ReservationStatus $from,
        ?ReservationStatus $to,
        string $title,
        ?string $note,
    ): void {
        $reservation->updates()->create([
            'status_from' => $from?->value,
            'status_to' => $to?->value,
            'title' => $title,
            'note' => $note,
            'is_visible_to_customer' => true,
        ]);
    }

    private function generateNumber(): string
    {
        return 'RSV-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
