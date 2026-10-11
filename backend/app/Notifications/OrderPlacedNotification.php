<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تأكيد الدفع واستلام الطلب.
 *
 * ShouldQueue مقصود: هذه تُرسل من داخل معالجة الخطّاف، وإرسال بريد
 * متزامن هناك يعني أن بطء خادم البريد قد يُوقّت الخطّاف ويجعل المزوّد
 * يعيد إرساله.
 */
final class OrderPlacedNotification extends Notification implements ShouldQueue
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
            ->subject('تأكيد طلبك رقم ' . $this->order->order_number . ' — وديان')
            ->greeting('أهلاً ' . $notifiable->name)
            ->line('استلمنا طلبك وتم تأكيد الدفع. شكراً لاقتنائك عملاً أصلياً.')
            ->line('رقم الطلب: ' . $this->order->order_number);

        foreach ($this->order->items as $item) {
            $mail->line('• ' . $item->title . ' — ' . Money::of($item->line_total)->formatted());
        }

        return $mail
            ->line('الإجمالي: ' . Money::of($this->order->grand_total)->formatted())
            ->action('تتبّع الطلب', $url)
            ->line('سنُعلمك فور شحن اللوحة.')
            ->salutation('مع التحية، فريق وديان');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order.placed',
            'title' => 'تم تأكيد طلبك',
            'body' => 'الطلب ' . $this->order->order_number . ' قيد التجهيز.',
            'orderNumber' => $this->order->order_number,
            'url' => '/account/orders/' . $this->order->order_number,
        ];
    }
}
