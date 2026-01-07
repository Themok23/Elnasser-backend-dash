@extends('layouts.admin.app')

@section('title', 'Ticket Details')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Ticket Details</h2>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Ticket #:</strong> {{ $ticket->ticket_number }}</p>
                    <p><strong>Status:</strong> {{ $statuses[$ticket->status] ?? $ticket->status }}</p>
                    <p><strong>Type:</strong> {{ $ticket->type?->name }}</p>
                    <p><strong>Inquiry Type:</strong> {{ $ticket->inquiry_type }}</p>
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


