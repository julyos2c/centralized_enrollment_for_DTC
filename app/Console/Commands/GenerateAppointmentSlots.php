<?php

namespace App\Console\Commands;

use App\Services\AppointmentScheduleService;
use Illuminate\Console\Command;

class GenerateAppointmentSlots extends Command
{
    protected $signature = 'appointments:generate-slots';
    protected $description = 'Generate appointment slots from the configured Registrar office schedule';

    public function handle(AppointmentScheduleService $service): int
    {
        $count = $service->generate();
        $this->info("Generated {$count} appointment slot(s).");
        return self::SUCCESS;
    }
}
