@extends('layouts.admin.app')

@section('title', 'Create Parent Ticket Type')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Create Parent Ticket Type</h2>
        <a href="{{ route('admin.support-ticket-types.index') }}" class="btn btn-outline-secondary">Back to Types</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <strong>Note:</strong> Create a parent type first. After creating, you can add child types (subcategories) from the "Manage Types" page.
            </div>

            <form method="POST" action="{{ route('admin.support-ticket-types.store-parent') }}">
                @csrf

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}" placeholder="e.g., Technical issue, Order issue, Service feedback">
                    @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                        Active
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">Create Parent</button>
            </form>
        </div>
    </div>
</div>
@endsection

