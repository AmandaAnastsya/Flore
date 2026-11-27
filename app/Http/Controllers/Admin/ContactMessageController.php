<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kotak masuk pesan Contact Us. */
class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), ContactMessage::STATUSES) ? $request->query('status') : null;

        return view('admin.messages.index', [
            'messages' => ContactMessage::with('user')->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")->latest()->latest('id')
                ->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => ContactMessage::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, ContactMessage $message)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContactMessage::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ], [], ['admin_note' => 'catatan']);

        $message->forceFill($data + ['handled_at' => $data['status'] === 'new' ? null : now()])->save();

        return back()->with('success', 'Pesan dari ' . $message->name . ' ditandai: ' . ContactMessage::STATUSES[$data['status']][0] . '.');
    }
}
