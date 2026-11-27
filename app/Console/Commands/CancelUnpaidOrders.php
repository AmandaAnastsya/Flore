<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderWorkflow;
use Illuminate\Console\Command;

/**
 * Batalkan pesanan yang tidak dibayar melewati batas waktu (Studio Settings → Pembayaran)
 * dan kembalikan stoknya. Dijadwalkan setiap 15 menit oleh Laravel Task Scheduler.
 */
class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid {--dry-run : Tampilkan saja tanpa membatalkan}';

    protected $description = 'Batalkan otomatis pesanan yang belum dibayar melewati batas waktu & kembalikan stok';

    public function handle(OrderWorkflow $workflow): int
    {
        $hours = (int) config('flore.payment.timeout_hours', 24);

        // saring kasar di database, keputusan akhir per pesanan memakai Order::isPaymentOverdue()
        $candidates = Order::where('status', 'pending_payment')
            ->whereIn('payment_status', ['unpaid', 'rejected'])
            ->where('created_at', '<=', now()->subHours($hours))
            ->orderBy('id')->get()
            ->filter->isPaymentOverdue();

        if ($this->option('dry-run')) {
            $candidates->each(fn (Order $o) => $this->line("{$o->order_number} · dibuat {$o->created_at->format('d M H:i')} · batas {$o->paymentDeadline()->format('d M H:i')}"));
            $this->info("{$candidates->count()} pesanan akan dibatalkan (batas {$hours} jam).");

            return self::SUCCESS;
        }

        $cancelled = $candidates->filter(fn (Order $o) => $workflow->expireUnpaid($o))->count();
        $this->info("{$cancelled} pesanan belum dibayar dibatalkan otomatis (batas {$hours} jam), stok dikembalikan.");

        return self::SUCCESS;
    }
}
