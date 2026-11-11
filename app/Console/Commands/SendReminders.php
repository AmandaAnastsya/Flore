<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Notifications\SpecialMomentReminder;
use App\Services\ReminderPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SendReminders extends Command
{
    protected $signature = 'reminders:send {--date= : Jalankan seolah-olah tanggal ini (Y-m-d), untuk uji coba}';

    protected $description = 'Smart Reminder: jadwalkan & kirim email pengingat momen spesial customer';

    public function handle(ReminderPlanner $planner): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today();

        $planned = $planner->plan($today);

        $due = Reminder::with(['user', 'order'])
            ->where('status', 'pending')
            ->whereDate('remind_at', '<=', $today)
            ->whereDate('occasion_date', '>=', $today)   // momen yang sudah lewat tidak dikirim lagi
            ->orderBy('remind_at')
            ->get();

        $sent = $failed = 0;

        foreach ($due as $reminder) {
            $user = $reminder->user;

            if (! $user || ! $user->reminders_enabled) {
                $reminder->update(['status' => 'failed', 'error_message' => 'Pengingat dinonaktifkan oleh customer.']);
                $failed++;
                continue;
            }

            $occasion = in_array($reminder->type, ['birthday', 'anniversary'], true)
                ? $reminder->type
                : $reminder->order?->occasion;

            try {
                $user->notify(new SpecialMomentReminder($reminder, $occasion));
                $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $reminder->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
                $failed++;
            }
        }

        $this->info("Tanggal {$today->toDateString()}: {$planned} pengingat baru dijadwalkan, {$sent} terkirim, {$failed} gagal.");

        return self::SUCCESS;
    }
}
