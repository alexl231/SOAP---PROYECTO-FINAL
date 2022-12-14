<?php

namespace App\Notifications;

use DateInterval;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class InviteEmail extends Notification
{

    /**
     * The callback that should be used to create the verify email URL.
     *
     * @var \Closure|null
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        return $this->buildMailMessage($verificationUrl);
    }

    /**
     * Set a callback that should be used when creating the email verification URL.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function createUrlUsing($callback)
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }

    /**
     * Get the verify email notification mail message for the given URL.
     *
     * @param  string  $url
     * @return MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject(Lang::get('You have a new invitation.'))
            ->line(Lang::get('Please click the button below to confirm the invitation.'))
            ->action(Lang::get('Accept the Invitation'), $url)
            ->line(Lang::get('If you did not want to accept the invitation, no further action is required.'));
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        return $this->temporarySignedRoute(
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60))
        );
    }

    /**
     * @param array $parameters
     * @param Carbon $expiration
     * @return string
     */
    private function temporarySignedRoute(array $parameters, Carbon $expiration): string
    {
        $key = env('APP_KEY');
        $parameters += ['expires' => $this->availableAt($expiration)];
        ksort($parameters);

        $urlVerification = env('URL_WEB_FRONTEND') . '/invitation?' . http_build_query($parameters);

        return env('URL_WEB_FRONTEND') . '/invitation?' . http_build_query(
            $parameters + ['signature' => hash_hmac('sha256', $urlVerification, $key)]
        );
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function availableAt($delay = 0)
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : Carbon::now()->addRealSeconds($delay)->getTimestamp();
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return \DateTimeInterface|int
     */
    protected function parseDateInterval($delay)
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }
}
