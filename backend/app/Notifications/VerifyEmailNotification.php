<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * رسالة تأكيد البريد بالعربية.
 *
 * الرابط يشير إلى الخادم لا إلى الواجهة: التوقيع يجب أن يُتحقَّق منه في
 * Laravel، ثم يُعاد التوجيه إلى الواجهة بنتيجة واضحة.
 */
final class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('تأكيد بريدك الإلكتروني — وديان')
            ->greeting('أهلاً ' . $notifiable->name)
            ->line('شكراً لانضمامك إلى وديان. اضغط الزر أدناه لتأكيد بريدك الإلكتروني وتفعيل حسابك.')
            ->action('تأكيد البريد الإلكتروني', $url)
            ->line('الرابط صالح لمدة ٦٠ دقيقة.')
            ->line('إن لم تُنشئ هذا الحساب فلا حاجة لأي إجراء.')
            ->salutation('مع التحية، فريق وديان');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ],
        );
    }
}
