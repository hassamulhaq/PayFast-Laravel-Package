<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeWithCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        $app = config('payfast-laravel-package.branding.app_name', config('app.name'));

        return new Envelope(subject: "Welcome to {$app} — your account is ready");
    }

    public function content(): Content
    {
        return new Content(
            view: 'payfast-laravel-package::emails.welcome-with-credentials',
            with: [
                'name' => $this->user->name ?? $this->user->email,
                'email' => $this->user->email,
                'password' => $this->plainPassword,
                'loginUrl' => url('/login'),
                'branding' => config('payfast-laravel-package.branding'),
            ],
        );
    }
}
