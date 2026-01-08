<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function parentTypes()
    {
        $parents = SupportTicketType::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $formatted = $parents->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->name,
            ];
        });

        return response()->json([
            'parents' => $formatted,
        ]);
    }

    public function childTypes()
    {
        $children = SupportTicketType::query()
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->with('parent:id,name')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        $formatted = $children->map(function ($child) {
            return [
                'id' => $child->id,
                'parent_id' => $child->parent_id,
                'parent_name' => $child->parent?->name,
                'name' => $child->name,
            ];
        });

        return response()->json([
            'children' => $formatted,
        ]);
    }

    public function types()
    {
        $types = SupportTicketType::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        $tree = $types
            ->whereNull('parent_id')
            ->values()
            ->map(function ($parent) use ($types) {
                return [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'children' => $types->where('parent_id', $parent->id)->values()->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'parent_id' => $child->parent_id,
                            'name' => $child->name,
                        ];
                    })->values(),
                ];
            })->values();

        // Flat list with id
        $typesFormatted = $types->map(function ($type) {
            return [
                'id' => $type->id,
                'parent_id' => $type->parent_id,
                'name' => $type->name,
            ];
        });

        return response()->json([
            'types' => $typesFormatted,      // flat list with 'value' instead of 'id'
            'type_tree' => $tree,   // grouped list for UI
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'support_ticket_type_id' => 'required|integer|exists:support_ticket_types,id',
            'branch_location_id' => 'required|integer|exists:branch_locations,id',
            'problem' => 'required|string|min:5',
            'contact_email' => 'required|email|max:191',
            'contact_phone' => 'required|string|max:40',
            'callback_requested' => 'sometimes|boolean',
            'callback_time' => 'nullable|date',
            'callback_notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max per image
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
        $ticket->branch_location_id = (int) $request->branch_location_id;
        $ticket->problem = (string) $request->problem;
        $ticket->contact_email = (string) $request->contact_email;
        $ticket->contact_phone = (string) $request->contact_phone;
        $ticket->callback_requested = $callbackRequested;
        $ticket->callback_time = $request->input('callback_time');
        $ticket->callback_notes = $request->input('callback_notes');
        $ticket->status = SupportTicketStatus::OPEN;
        $ticket->save();

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
            'sender_type' => 'customer',
            'sender_id' => auth('api')->id(),
            'message' => (string) $request->problem,
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

        $ticket->load(['type:id,name', 'branchLocation:id,name', 'messages']);

        return response()->json([
            'message' => 'Ticket created successfully',
            'ticket' => $ticket,
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
            ->with(['type:id,name', 'branchLocation:id,name', 'messages'])
            ->findOrFail($id);

        return response()->json(['ticket' => $ticket]);
    }

    public function messages($id)
    {
        $ticket = SupportTicket::query()
            ->where('user_id', auth('api')->id())
            ->findOrFail($id);

        $messages = $ticket->messages()->get();

        return response()->json([
            'ticket_id' => $ticket->id,
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request, $id)
    {
        $ticket = SupportTicket::query()
            ->where('user_id', auth('api')->id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max per image
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => \App\CentralLogics\Helpers::error_processor($validator)], 403);
        }

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

        $msg = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'sender_id' => auth('api')->id(),
            'message' => (string) $request->message,
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

        return response()->json([
            'message' => 'Message sent',
            'data' => $msg,
        ]);
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


