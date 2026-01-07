<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupportTicketInquiryType;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function types()
    {
        $types = SupportTicketType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['types' => $types]);
    }

    public function inquiryTypes()
    {
        return response()->json([
            'inquiry_types' => SupportTicketInquiryType::labels(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'support_ticket_type_id' => 'required|integer|exists:support_ticket_types,id',
            'inquiry_type' => 'required|string|in:' . implode(',', SupportTicketInquiryType::values()),
            'branch_location_id' => 'required|integer|exists:branch_locations,id',
            'problem' => 'required|string|min:5',
            'contact_email' => 'required|email|max:191',
            'contact_phone' => 'required|string|max:40',
            'callback_requested' => 'sometimes|boolean',
            'callback_time' => 'nullable|date',
            'callback_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => \App\CentralLogics\Helpers::error_processor($validator)], 403);
        }

        $type = SupportTicketType::query()
            ->where('is_active', true)
            ->findOrFail((int) $request->support_ticket_type_id);

        $callbackRequested = (bool) $request->input('callback_requested', false);
        if ($callbackRequested && !$request->filled('callback_time')) {
            return response()->json([
                'errors' => [
                    ['code' => 'callback_time', 'message' => 'Callback time is required when callback is requested.'],
                ],
            ], 403);
        }

        $ticket = new SupportTicket();
        $ticket->ticket_number = $this->generateTicketNumber();
        $ticket->user_id = auth('api')->id();
        $ticket->support_ticket_type_id = $type->id;
        $ticket->inquiry_type = (string) $request->inquiry_type;
        $ticket->branch_location_id = (int) $request->branch_location_id;
        $ticket->problem = (string) $request->problem;
        $ticket->contact_email = (string) $request->contact_email;
        $ticket->contact_phone = (string) $request->contact_phone;
        $ticket->callback_requested = $callbackRequested;
        $ticket->callback_time = $request->input('callback_time');
        $ticket->callback_notes = $request->input('callback_notes');
        $ticket->status = SupportTicketStatus::OPEN;
        $ticket->save();

        return response()->json([
            'message' => 'Ticket created successfully',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', auth('api')->id())
            ->with(['type:id,name', 'branchLocation:id,name'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($tickets);
    }

    public function show($id)
    {
        $ticket = SupportTicket::query()
            ->where('user_id', auth('api')->id())
            ->with(['type:id,name', 'branchLocation:id,name'])
            ->findOrFail($id);

        return response()->json(['ticket' => $ticket]);
    }

    public function statusByNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_number' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => \App\CentralLogics\Helpers::error_processor($validator)], 403);
        }

        $ticket = SupportTicket::query()
            ->where('user_id', auth('api')->id())
            ->where('ticket_number', $request->ticket_number)
            ->first();

        if (!$ticket) {
            return response()->json([
                'errors' => [
                    ['code' => 'not_found', 'message' => 'Ticket not found.'],
                ],
            ], 404);
        }

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'resolved_at' => $ticket->resolved_at,
            'updated_at' => $ticket->updated_at,
        ]);
    }

    private function generateTicketNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
            if (!SupportTicket::query()->where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'TKT-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
    }
}


