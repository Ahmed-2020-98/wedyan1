<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * رسالة إعادة تعيين كلمة المرور بالعربية.
 *
 * هنا الرابط يشير إلى الواجهة لا إلى الخادم، لأن المستخدم يحتاج نموذجاً
 * يكتب فيه كلمة المرور الجديدة؛ والواجهة هي من ترسل الرمز إلى الخادم.
 */
final class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('platform.urls.web'), '/'),
            $this->token,
            urlencode((string) $notifiable->getEmailForPasswordReset()),
        );

        $minutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — وديان')
            ->greeting('أهلاً ' . $notifiable->name)
            ->line('وصلنا طلب لإعادة تعيين كلمة مرور حسابك.')
            ->action('إعادة تعيين كلمة المرور', $url)
            ->line("الرابط صالح لمدة {$minutes} دقيقة.")
            ->line('إن لم تطلب ذلك فتجاهل هذه الرسالة؛ لن يتغيّر شيء في حسابك.')
            ->salutation('مع التحية، فريق وديان');
    }
}
