<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Reservation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReservationStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Reservation $reservation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('platform.urls.web'), '/')
            . '/account/reservations/' . $this->reservation->reservation_number;

        $mail = (new MailMessage)
            ->subject('تحديث على حجزك ' . $this->reservation->reservation_number . ' — وديان')
            ->greeting('أهلاً ' . $notifiable->name)
            ->line('حالة حجزك الآن: ' . $this->reservation->status->label());

        if ($this->reservation->status->awaitsPayment() && $this->reservation->amountDueNow() > 0) {
            $mail->line('المبلغ المستحق: ' . Money::of($this->reservation->amountDueNow())->formatted());
        }

        if ($this->reservation->artist_note) {
            $mail->line('ملاحظة الفنان: ' . $this->reservation->artist_note);
        }

        return $mail->action('عرض الحجز', $url)->salutation('مع التحية، فريق وديان');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reservation.status',
            'title' => 'تحديث على حجزك',
            'body' => 'الحجز ' . $this->reservation->reservation_number . ': ' . $this->reservation->status->label(),
            'reservationNumber' => $this->reservation->reservation_number,
            'url' => '/account/reservations/' . $this->reservation->reservation_number,
        ];
    }
}
