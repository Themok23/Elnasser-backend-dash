@extends('layouts.admin.app')

@section('title', 'Mission Submissions')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Mission Submissions</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.missions.index') }}" class="btn btn-outline-secondary">Missions</a>
            <a href="{{ route('admin.missions.create') }}" class="btn btn-primary">Create Mission</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status')=='pending'?'selected':'' }}>Pending</option>
                        <option value="approved" {{ request('status')=='approved'?'selected':'' }}>Approved</option>
                        <option value="rejected" {{ request('status')=='rejected'?'selected':'' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="mission_id" class="form-control">
                        <option value="">All Missions</option>
                        @foreach($missions as $m)
                            <option value="{{ $m->id }}" {{ (string)request('mission_id')===(string)$m->id ? 'selected' : '' }}>
                                #{{ $m->id }} - {{ $m->name }}
                            </option>
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
                            <th>Mission</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Points</th>
                            <th>Submitted</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>#{{ $s->mission_id }} - {{ $s->mission?->name }}</td>
                                <td>
                                    {{ $s->user?->f_name }} {{ $s->user?->l_name }}<br>
                                    <small class="text-muted">{{ $s->user?->phone }}</small>
                                </td>
                                <td>
                                    @if($s->status === 'pending')
                                        <span class="badge badge-soft-warning">Pending</span>
                                    @elseif($s->status === 'approved')
                                        <span class="badge badge-soft-success">Approved</span>
                                    @else
                                        <span class="badge badge-soft-danger">Rejected</span>
                                    @endif
                                </td>
                                <td>
                                    @if($s->status === 'approved')
                                        {{ $s->approved_points ?? $s->mission?->points }}
                                    @else
                                        {{ $s->mission?->points }}
                                    @endif
                                </td>
                                <td>{{ $s->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.missions.submissions.show', $s->id) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">No submissions found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {!! $submissions->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection




