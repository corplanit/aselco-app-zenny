<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WalletLoadedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $customer,
        public WalletTransaction $transaction,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AST loaded to your ASELCO wallet',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet-loaded',
        );
    }
}
