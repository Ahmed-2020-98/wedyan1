<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('platform.urls.web'), '/')
            . '/account/orders/' . $this->order->order_number;

        $mail = (new MailMessage)
            ->subject('تحديث على طلبك ' . $this->order->order_number . ' — وديان')
            ->greeting('أهلاً ' . $notifiable->name)
            ->line('حالة طلبك الآن: ' . $this->order->status->label());

        if ($this->order->status === OrderStatus::Shipped && $this->order->tracking_number) {
            $mail->line('رقم التتبّع: ' . $this->order->tracking_number);
        }

        return $mail->action('عرض الطلب', $url)->salutation('مع التحية، فريق وديان');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order.status',
            'title' => 'تحديث على طلبك',
            'body' => 'الطلب ' . $this->order->order_number . ': ' . $this->order->status->label(),
            'orderNumber' => $this->order->order_number,
            'url' => '/account/orders/' . $this->order->order_number,
        ];
    }
}
