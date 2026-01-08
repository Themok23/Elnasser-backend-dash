@extends('layouts.admin.app')

@section('title', 'Support Ticket Types')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Support Ticket Types</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.support-ticket-types.create-parent') }}" class="btn btn-primary">Create Parent</a>
            <a href="{{ route('admin.support-ticket-types.create') }}" class="btn btn-outline-primary">Create Child</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Parent Types</h5>
        </div>
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
                            <th>Active</th>
                            <th>Children Count</th>
                            <th>Created At</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parents as $parent)
                            <tr>
                                <td>{{ $parent->id }}</td>
                                <td><strong>{{ $parent->name }}</strong></td>
                                <td>
                                    @if($parent->is_active)
                                        <span class="badge badge-soft-success">Yes</span>
                                    @else
                                        <span class="badge badge-soft-danger">No</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-soft-info">{{ $children->where('parent_id', $parent->id)->count() }}</span>
                                </td>
                                <td>{{ optional($parent->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.support-ticket-types.edit-parent', $parent->id) }}">Edit</a>
                                    <form action="{{ route('admin.support-ticket-types.destroy-parent', $parent->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete parent type? You must delete or move all children first.')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">No parent types found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Child Types</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Parent</th>
                            <th>Name</th>
                            <th>Active</th>
                            <th>Created At</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($children as $child)
                            <tr>
                                <td>{{ $child->id }}</td>
                                <td>
                                    <span class="badge badge-soft-primary">{{ $child->parent?->name ?? 'N/A' }}</span>
                                </td>
                                <td>{{ $child->name }}</td>
                                <td>
                                    @if($child->is_active)
                                        <span class="badge badge-soft-success">Yes</span>
                                    @else
                                        <span class="badge badge-soft-danger">No</span>
                                    @endif
                                </td>
                                <td>{{ optional($child->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.support-ticket-types.edit', $child->id) }}">Edit</a>
                                    <form action="{{ route('admin.support-ticket-types.destroy', $child->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete type?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">No child types found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
