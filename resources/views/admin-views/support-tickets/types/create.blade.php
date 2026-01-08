@extends('layouts.admin.app')

@section('title', 'Create Ticket Type')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Create Ticket Type (Child)</h2>
        <a href="{{ route('admin.support-ticket-types.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.support-ticket-types.store') }}">
                @csrf

                <div class="form-group">
                    <label>Parent <span class="text-danger">*</span></label>
                    <select name="parent_id" class="form-control" required>
                        <option value="">-- Select Parent --</option>
                        @foreach($parents as $p)
                            <option value="{{ $p->id }}" {{ old('parent_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('parent_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">Select a parent type. If no parents exist, <a href="{{ route('admin.support-ticket-types.create-parent') }}">create a parent first</a>.</small>
                </div>

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name') }}" placeholder="e.g., App issue, Points issue">
                    @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                        Active
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">Create Type</button>
            </form>
        </div>
    </div>
</div>
@endsection


