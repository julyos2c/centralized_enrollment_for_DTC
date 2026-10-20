<?php

namespace App\Console\Commands;

use App\Services\DocumentPaymentNotificationOutboxService;
use Illuminate\Console\Command;
use App\Models\DocumentPaymentNotificationOutbox;

class ProcessDocumentPaymentNotifications extends Command
{
    protected $signature = 'document-payment-notifications:process {--limit=100}';

    protected $description = 'Deliver pending document payment notifications from the transactional outbox';

    public function handle(DocumentPaymentNotificationOutboxService $outbox): int
    {
        $delivered = $outbox->processDue((int) $this->option('limit'));
        $pending = DocumentPaymentNotificationOutbox::where('status', 'pending')->count();
        $failed = DocumentPaymentNotificationOutbox::where('status', 'failed')->count();
        $this->info($delivered . ' delivered; ' . $pending . ' pending; ' . $failed . ' failed and requiring review.');

        return self::SUCCESS;
    }
}
