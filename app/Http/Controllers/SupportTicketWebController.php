<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketStatus;
use App\Models\BranchLocation;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupportTicketWebController extends Controller
{
    private const SESSION_KEY = 'support_ticket_draft';

    public function step1()
    {
        $types = SupportTicketType::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        $draft = session()->get(self::SESSION_KEY, []);

        return view('support-tickets.step-1', compact('types', 'draft'));
    }

    public function step1Store(Request $request)
    {
        $data = $request->validate([
            'support_ticket_type_id' => 'required|integer|exists:support_ticket_types,id',
        ]);

        $type = SupportTicketType::query()
            ->where('is_active', true)
            ->findOrFail($data['support_ticket_type_id']);

        $draft = session()->get(self::SESSION_KEY, []);
        $draft['support_ticket_type_id'] = $type->id;

        session()->put(self::SESSION_KEY, $draft);

        return redirect()->route('support-tickets.step2');
    }

    public function step2(Request $request)
    {
        $draft = session()->get(self::SESSION_KEY, []);
        if (!Arr::get($draft, 'support_ticket_type_id')) {
            return redirect()->route('support-tickets.step1');
        }

        $branches = BranchLocation::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderBy('rank')
            ->orderBy('name')
            ->paginate(15);

        return view('support-tickets.step-2', compact('branches', 'draft'));
    }

    public function step2Store(Request $request)
    {
        $draft = session()->get(self::SESSION_KEY, []);
        if (!Arr::get($draft, 'support_ticket_type_id')) {
            return redirect()->route('support-tickets.step1');
        }

        $data = $request->validate([
            'branch_location_id' => 'required|integer|exists:branch_locations,id',
        ]);

        $draft['branch_location_id'] = $data['branch_location_id'];
        session()->put(self::SESSION_KEY, $draft);

        return redirect()->route('support-tickets.step3');
    }

    public function step3()
    {
        $draft = session()->get(self::SESSION_KEY, []);
        if (!Arr::get($draft, 'support_ticket_type_id') || !Arr::get($draft, 'branch_location_id')) {
            return redirect()->route('support-tickets.step1');
        }

        return view('support-tickets.step-3', compact('draft'));
    }

    public function step3Store(Request $request)
    {
        $draft = session()->get(self::SESSION_KEY, []);
        if (!Arr::get($draft, 'support_ticket_type_id') || !Arr::get($draft, 'branch_location_id')) {
            return redirect()->route('support-tickets.step1');
        }

        $data = $request->validate([
            'problem' => 'required|string|min:5',
            'contact_email' => 'required|email|max:191',
            'contact_phone' => 'required|string|max:40',
            'callback_requested' => 'nullable|boolean',
            'callback_time' => 'nullable|date',
            'callback_notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        $data['callback_requested'] = (bool) ($request->input('callback_requested', 0));
        if ($data['callback_requested'] && empty($data['callback_time'])) {
            return back()->withErrors(['callback_time' => 'Callback time is required when callback is requested.'])->withInput();
        }

        // Handle image uploads temporarily
        $uploadedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = \App\Traits\FileManagerTrait::upload('support-tickets/', 'webp', $image);
                $disk = \App\Traits\FileManagerTrait::getDisk();
                $url = $disk === 's3' 
                    ? \Illuminate\Support\Facades\Storage::disk('s3')->url('support-tickets/' . $imageName)
                    : asset('public/storage/support-tickets/' . $imageName);
                $uploadedImages[] = [
                    'type' => 'image',
                    'path' => 'support-tickets/' . $imageName,
                    'url' => $url,
                ];
            }
        }

        $draft = array_merge($draft, [
            'problem' => $data['problem'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'callback_requested' => $data['callback_requested'],
            'callback_time' => $data['callback_time'] ?? null,
            'callback_notes' => $data['callback_notes'] ?? null,
            'images' => $uploadedImages,
        ]);

        session()->put(self::SESSION_KEY, $draft);

        return redirect()->route('support-tickets.review');
    }

    public function review()
    {
        $draft = session()->get(self::SESSION_KEY, []);
        foreach (['support_ticket_type_id', 'branch_location_id', 'problem', 'contact_email', 'contact_phone'] as $key) {
            if (!Arr::get($draft, $key)) {
                return redirect()->route('support-tickets.step1');
            }
        }

        $type = SupportTicketType::find($draft['support_ticket_type_id']);
        $branch = BranchLocation::find($draft['branch_location_id']);

        return view('support-tickets.review', compact('draft', 'type', 'branch'));
    }

    public function submit(Request $request)
    {
        $draft = session()->get(self::SESSION_KEY, []);
        foreach (['support_ticket_type_id', 'branch_location_id', 'problem', 'contact_email', 'contact_phone'] as $key) {
            if (!Arr::get($draft, $key)) {
                return redirect()->route('support-tickets.step1');
            }
        }

        $request->validate([
            'confirm' => 'required|in:1',
        ]);

        $ticket = new SupportTicket();
        $ticket->ticket_number = $this->generateTicketNumber();
        $ticket->user_id = null;
        $ticket->support_ticket_type_id = (int) $draft['support_ticket_type_id'];
        $ticket->branch_location_id = (int) $draft['branch_location_id'];
        $ticket->problem = (string) $draft['problem'];
        $ticket->contact_email = (string) $draft['contact_email'];
        $ticket->contact_phone = (string) $draft['contact_phone'];
        $ticket->callback_requested = (bool) ($draft['callback_requested'] ?? false);
        $ticket->callback_time = $draft['callback_time'] ?? null;
        $ticket->callback_notes = $draft['callback_notes'] ?? null;
        $ticket->status = SupportTicketStatus::OPEN;
        $ticket->save();

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'message' => (string) $draft['problem'],
            'attachments' => !empty($draft['images']) ? $draft['images'] : null,
        ]);

        session()->forget(self::SESSION_KEY);

        return view('support-tickets.success', ['ticket' => $ticket]);
    }

    public function reset()
    {
        session()->forget(self::SESSION_KEY);
        return redirect()->route('support-tickets.step1');
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


