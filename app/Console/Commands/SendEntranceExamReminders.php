<?php

namespace App\Console\Commands;

use App\Models\EntranceExamBooking;
use App\Services\AdmissionNotifier;
use Illuminate\Console\Command;

class SendEntranceExamReminders extends Command
{
    protected $signature = 'admissions:send-exam-reminders';

    protected $description = 'Email applicants whose confirmed entrance exam is tomorrow (each booking is reminded once).';

    public function handle(AdmissionNotifier $notifier): int
    {
        $sent = 0;
        EntranceExamBooking::where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereHas('session', fn ($query) => $query->where('status', '!=', 'cancelled')->whereDate('exam_date', today()->addDay()))
            ->with(['session', 'application.user'])
            ->chunkById(100, function ($bookings) use ($notifier, &$sent) {
                foreach ($bookings as $booking) {
                    // Claim the reminder first so an overlapping run cannot send it twice.
                    $claimed = EntranceExamBooking::whereKey($booking->id)->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => now()]);
                    if (!$claimed) {
                        continue;
                    }
                    $session = $booking->session;
                    $notifier->applicant(
                        $booking->application,
                        'Reminder: entrance exam tomorrow',
                        "Your entrance examination is tomorrow, {$session->scheduleLabel()}, at {$session->venue}. Please arrive early and bring a valid ID and your reference number."
                            . ($session->notes ? ' ' . $session->notes : ''),
                        'info'
                    );
                    $sent++;
                }
            });

        $this->info("Sent {$sent} entrance exam reminder(s).");

        return self::SUCCESS;
    }
}
