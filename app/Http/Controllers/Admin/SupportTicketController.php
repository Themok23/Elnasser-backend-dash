<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
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
        $ticket = SupportTicket::with(['type', 'branchLocation', 'user'])->findOrFail($id);
        $statuses = SupportTicketStatus::labels();

        return view('admin-views.support-tickets.tickets.show', compact('ticket', 'statuses'));
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


