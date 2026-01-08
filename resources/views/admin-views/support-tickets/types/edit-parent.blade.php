@extends('layouts.admin.app')

@section('title', 'Edit Parent Ticket Type')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Edit Parent Ticket Type</h2>
        <a href="{{ route('admin.support-ticket-types.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.support-ticket-types.update-parent', $type->id) }}">
                @csrf

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name', $type->name) }}" placeholder="e.g., Technical issue">
                    @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $type->is_active) ? 'checked' : '' }}>
                        Active
                    </label>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Update Parent</button>
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete this parent type? This action cannot be undone.')) { document.getElementById('delete-form').submit(); }">Delete</button>
                </div>
            </form>

            <form id="delete-form" method="POST" action="{{ route('admin.support-ticket-types.destroy-parent', $type->id) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    @if($type->children()->exists())
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Children Types</h5>
                <p class="text-muted">This parent has {{ $type->children()->count() }} child type(s). You must delete or move them before deleting this parent.</p>
                <ul class="list-group">
                    @foreach($type->children as $child)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ $child->name }}
                            <a href="{{ route('admin.support-ticket-types.edit', $child->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection

