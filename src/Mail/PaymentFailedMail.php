<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentTransaction $txn) {}

    public function envelope(): Envelope
    {
        $app = config('payfast-laravel-package.branding.app_name', config('app.name'));

        return new Envelope(subject: "Payment failed — {$app} ({$this->txn->basket_id})");
    }

    public function content(): Content
    {
        return new Content(
            view: 'payfast-laravel-package::emails.payment-failed',
            with: [
                'txn' => $this->txn,
                'branding' => config('payfast-laravel-package.branding'),
            ],
        );
    }
}
