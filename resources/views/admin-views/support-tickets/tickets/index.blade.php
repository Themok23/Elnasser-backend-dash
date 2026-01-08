@extends('layouts.admin.app')

@section('title', 'Support Tickets')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Support Tickets</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.support-ticket-types.index') }}" class="btn btn-outline-primary">Manage Types</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by ticket # / email / phone" value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" type="submit">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Ticket #</th>
                            <th>Type</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->id }}</td>
                                <td>{{ $ticket->ticket_number }}</td>
                                <td>{{ $ticket->type?->name }}</td>
                                <td>{{ $ticket->branchLocation?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-soft-info">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                                </td>
                                <td>{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.support-tickets.show', $ticket->id) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">No tickets found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {!! $tickets->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection


