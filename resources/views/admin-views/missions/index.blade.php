@extends('layouts.admin.app')

@section('title', 'Missions')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Missions</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.missions.submissions.index') }}" class="btn btn-outline-primary">Submissions</a>
            <a href="{{ route('admin.missions.create') }}" class="btn btn-primary">Create Mission</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" type="submit">Search</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Points</th>
                            <th>Active</th>
                            <th>Requires Proof</th>
                            <th>Max / User</th>
                            <th>Start</th>
                            <th>End</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($missions as $mission)
                            <tr>
                                <td>{{ $mission->id }}</td>
                                <td>{{ $mission->name }}</td>
                                <td>{{ $mission->points }}</td>
                                <td>
                                    @if($mission->is_active)
                                        <span class="badge badge-soft-success">Yes</span>
                                    @else
                                        <span class="badge badge-soft-danger">No</span>
                                    @endif
                                </td>
                                <td>{{ $mission->requires_proof ? 'Yes' : 'No' }}</td>
                                <td>{{ $mission->max_per_user }}</td>
                                <td>{{ optional($mission->start_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ optional($mission->end_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.missions.edit', $mission->id) }}">Edit</a>
                                    <a class="btn btn-sm btn-outline-warning" href="{{ route('admin.missions.toggle-status', $mission->id) }}">
                                        {{ $mission->is_active ? 'Disable' : 'Enable' }}
                                    </a>
                                    <form action="{{ route('admin.missions.destroy', $mission->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete mission?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center">No missions found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {!! $missions->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection




