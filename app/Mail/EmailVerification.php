<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\EmailVerificationCode;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationCode;
    public $userName;

    /**
     * Create a new message instance.
     */
    public function __construct(EmailVerificationCode $verificationCode, ?string $userName = null)
    {
        $this->verificationCode = $verificationCode;
        $this->userName = $userName ?? 'User';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject('Email Verification Code - 5str')
                    ->view('emails.verification')
                    ->with([
                        'code' => $this->verificationCode->code,
                        'name' => $this->userName,
                        'expiresAt' => $this->verificationCode->expires_at,
                        'appName' => config('app.name'),
                    ]);
    }
}