@extends('layouts.admin.app')

@section('title', 'Edit Mission')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Edit Mission</h2>
        <a href="{{ route('admin.missions.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.missions.update', $mission->id) }}">
                @csrf

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required value="{{ old('name', $mission->name) }}">
                </div>

                <div class="form-group">
                    <label>Description (optional)</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $mission->description) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Points</label>
                        <input type="number" name="points" class="form-control" min="0" required value="{{ old('points', $mission->points) }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Max per user</label>
                        <input type="number" name="max_per_user" class="form-control" min="1" required value="{{ old('max_per_user', $mission->max_per_user) }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Start at (optional)</label>
                        <input type="datetime-local" name="start_at" class="form-control" value="{{ old('start_at', optional($mission->start_at)->format('Y-m-d\\TH:i')) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>End at (optional)</label>
                        <input type="datetime-local" name="end_at" class="form-control" value="{{ old('end_at', optional($mission->end_at)->format('Y-m-d\\TH:i')) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $mission->is_active) ? 'checked' : '' }}>
                        Active
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="requires_proof" value="1" {{ old('requires_proof', $mission->requires_proof) ? 'checked' : '' }}>
                        Requires proof screenshot
                    </label>
                </div>

                <div class="form-group">
                    <label>Proof instructions (optional)</label>
                    <textarea name="proof_instructions" class="form-control" rows="3">{{ old('proof_instructions', $mission->proof_instructions) }}</textarea>
                </div>

                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</div>
@endsection




