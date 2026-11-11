<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\ReminderPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/** Log Smart Reminder + pemicu manual (use case "View Reminder Logs" & "Trigger Manual Reminder"). */
class ReminderController extends Controller
{
    public function index(Request $request, ReminderPlanner $planner)
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Reminder::STATUS_BADGES) ? $status : null;

        return view('admin.reminders.index', [
            'reminders' => Reminder::with('user')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('remind_at')->latest('id')
                ->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => Reminder::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'upcoming' => $planner->scheduleOverview(),
        ]);
    }

    public function run()
    {
        Artisan::call('reminders:send');

        return back()->with('success', trim(Artisan::output()));
    }
}
