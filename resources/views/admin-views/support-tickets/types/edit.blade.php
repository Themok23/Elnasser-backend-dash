@extends('layouts.admin.app')

@section('title', 'Edit Ticket Type')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Edit Ticket Type</h2>
        <a href="{{ route('admin.support-ticket-types.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.support-ticket-types.update', $type->id) }}">
                @csrf

                @if($type->parent_id === null)
                    {{-- Editing a Parent --}}
                    <div class="alert alert-info">
                        <strong>Parent Type:</strong> This is a parent type. You can only edit its name and active status.
                    </div>

                    <input type="hidden" name="parent_id" value="">

                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $type->name) }}">
                        @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $type->is_active) ? 'checked' : '' }}>
                            Active
                        </label>
                    </div>
                @else
                    {{-- Editing a Child --}}
                    <div class="form-group">
                        <label>Parent <span class="text-danger">*</span></label>
                        <select name="parent_id" class="form-control" required>
                            <option value="">-- Select Parent --</option>
                            @foreach($parents as $p)
                                <option value="{{ $p->id }}" {{ old('parent_id', $type->parent_id) == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $type->name) }}">
                        @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $type->is_active) ? 'checked' : '' }}>
                            Active
                        </label>
                    </div>
                @endif

                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</div>
@endsection


