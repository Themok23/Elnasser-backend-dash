@extends('layouts.admin.app')

@section('title', 'Ticket Details')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Ticket Details</h2>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if($ticket->user)
    <div class="card mb-3">
        <div class="card-body">
            <h4 class="mb-3">User Details</h4>
            <div class="row">
                <div class="col-md-3 text-center mb-3 mb-md-0">
                    <img class="rounded-circle" 
                         src="{{ $ticket->user->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg') }}" 
                         alt="User Image" 
                         style="width: 100px; height: 100px; object-fit: cover;"
                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}">
                </div>
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> 
                                @if($ticket->user->f_name || $ticket->user->l_name)
                                    {{ trim(($ticket->user->f_name ?? '') . ' ' . ($ticket->user->l_name ?? '')) }}
                                @else
                                    <span class="text-muted">Incomplete Profile</span>
                                @endif
                            </p>
                            <p><strong>Email:</strong> 
                                <a href="mailto:{{ $ticket->user->email }}">{{ $ticket->user->email ?? '-' }}</a>
                            </p>
                            <p><strong>Phone:</strong> 
                                <a href="tel:{{ $ticket->user->phone }}">{{ $ticket->user->phone ?? '-' }}</a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>User ID:</strong> {{ $ticket->user->id }}</p>
                            <p><strong>Account Status:</strong> 
                                <span class="badge badge-{{ $ticket->user->status ? 'success' : 'danger' }}">
                                    {{ $ticket->user->status ? 'Active' : 'Blocked' }}
                                </span>
                            </p>
                            @if($ticket->user->loyalty_point !== null)
                            <p><strong>Loyalty Points:</strong> {{ number_format($ticket->user->loyalty_point ?? 0) }}</p>
                            @endif
                            @if($ticket->user->tier_level)
                            <p><strong>Tier Level:</strong> 
                                <span class="badge badge-info text-capitalize">{{ $ticket->user->tier_level }}</span>
                            </p>
                            @endif
                            <p><strong>Member Since:</strong> {{ $ticket->user->created_at ? $ticket->user->created_at->format('Y-m-d') : '-' }}</p>
                        </div>
                    </div>
                    @if($ticket->user->id)
                    <div class="mt-2">
                        <a href="{{ route('admin.users.customer.view', [$ticket->user->id]) }}" class="btn btn-sm btn-outline-primary">
                            View Full Profile
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Ticket #:</strong> {{ $ticket->ticket_number }}</p>
                    <p><strong>Status:</strong> {{ $statuses[$ticket->status] ?? $ticket->status }}</p>
                    <p><strong>Type:</strong> {{ $ticket->type?->name }}</p>
                    <p><strong>Branch:</strong> {{ $ticket->branchLocation?->name ?? '-' }}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Contact Email:</strong> {{ $ticket->contact_email }}</p>
                    <p><strong>Contact Phone:</strong> {{ $ticket->contact_phone }}</p>
                    <p><strong>Callback Requested:</strong> {{ $ticket->callback_requested ? 'Yes' : 'No' }}</p>
                    <p><strong>Callback Time:</strong> {{ $ticket->callback_time ? $ticket->callback_time->format('Y-m-d H:i') : '-' }}</p>
                    <p><strong>Callback Notes:</strong> {{ $ticket->callback_notes ?? '-' }}</p>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <h4 class="mb-2">Problem</h4>
                <div class="p-3 border rounded bg-light">
                    {!! nl2br(e($ticket->problem)) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h4 class="mb-3">Conversation</h4>

            <div class="p-3 border rounded" style="max-height: 420px; overflow:auto; background:#fafafa;">
                @forelse($ticket->messages as $msg)
                    @php
                        $isAdmin = $msg->sender_type === 'admin';
                        $isCustomer = $msg->sender_type === 'customer';
                    @endphp
                    <div class="mb-3 d-flex {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }}">
                        <div style="max-width: 75%;" class="p-3 rounded {{ $isAdmin ? 'bg-primary text-white' : 'bg-white' }}">
                            <div class="small {{ $isAdmin ? 'text-white-50' : 'text-muted' }}">
                                {{ ucfirst($msg->sender_type) }} • {{ optional($msg->created_at)->format('Y-m-d H:i') }}
                            </div>
                            <div class="mt-1">{!! nl2br(e($msg->message)) !!}</div>
                            @if($msg->attachments && is_array($msg->attachments) && count($msg->attachments) > 0)
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    @foreach($msg->attachments as $attachment)
                                        @if(isset($attachment['type']) && $attachment['type'] === 'image' && !empty($attachment['url'] ?? $attachment['path'] ?? null))
                                            <a href="{{ $attachment['url'] ?? asset('public/storage/' . $attachment['path']) }}" target="_blank" class="d-inline-block">
                                                <img src="{{ $attachment['url'] ?? asset('public/storage/' . $attachment['path']) }}" alt="Attachment" style="max-width: 150px; max-height: 150px; border-radius: 4px; cursor: pointer;" class="img-thumbnail" onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'">
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted">No messages yet</div>
                @endforelse
            </div>

            <form class="mt-3" method="POST" action="{{ route('admin.support-tickets.reply', $ticket->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label>Reply</label>
                    <textarea name="message" class="form-control" rows="3" required placeholder="Type your reply...">{{ old('message') }}</textarea>
                    @error('message')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Attach Images (optional)</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                    <small class="form-text text-muted">You can select multiple images. Max 5MB per image.</small>
                    @error('images.*')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Send</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">Update Status</h4>
            <form method="POST" action="{{ route('admin.support-tickets.status', $ticket->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $ticket->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>Admin Notes (optional)</label>
                    <textarea name="admin_notes" class="form-control" rows="3">{{ old('admin_notes', $ticket->admin_notes) }}</textarea>
                    @error('admin_notes')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</div>
@endsection


