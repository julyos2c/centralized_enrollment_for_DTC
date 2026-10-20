<?php

namespace App\Mail;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentRequestUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public DocumentRequest $documentRequest, public string $eventMessage)
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject('DTC EMS document request update')
            ->view('emails.document-request-update');
    }
}
