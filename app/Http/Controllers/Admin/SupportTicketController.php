<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::query()->with(['type', 'branchLocation', 'user']);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('contact_email', 'like', '%' . $search . '%')
                    ->orWhere('contact_phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->orderByDesc('id')->paginate(config('default_pagination', 15));
        $statuses = SupportTicketStatus::labels();

        return view('admin-views.support-tickets.tickets.index', compact('tickets', 'statuses'));
    }

    public function show($id)
    {
        $ticket = SupportTicket::with(['type', 'branchLocation', 'user.storage', 'messages'])->findOrFail($id);
        $statuses = SupportTicketStatus::labels();

        return view('admin-views.support-tickets.tickets.show', compact('ticket', 'statuses'));
    }

    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'message' => 'required|string|min:1|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        // Handle image uploads
        $attachments = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = \App\Traits\FileManagerTrait::upload('support-tickets/', 'webp', $image);
                $disk = \App\Traits\FileManagerTrait::getDisk();
                $url = $disk === 's3' 
                    ? \Illuminate\Support\Facades\Storage::disk('s3')->url('support-tickets/' . $imageName)
                    : asset('public/storage/support-tickets/' . $imageName);
                $attachments[] = [
                    'type' => 'image',
                    'path' => 'support-tickets/' . $imageName,
                    'url' => $url,
                ];
            }
        }

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'sender_id' => auth('admin')->id(),
            'message' => $data['message'],
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

        Toastr::success('Reply sent');
        return back();
    }

    public function updateStatus(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', SupportTicketStatus::values()),
            'admin_notes' => 'nullable|string',
        ]);

        $ticket->status = $data['status'];
        $ticket->admin_notes = $data['admin_notes'] ?? $ticket->admin_notes;

        if (in_array($ticket->status, [SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED], true)) {
            $ticket->resolved_at = $ticket->resolved_at ?? now();
            $ticket->created_by_admin_id = auth('admin')->id();
        }

        $ticket->save();

        Toastr::success('Ticket updated successfully');
        return back();
    }
}


