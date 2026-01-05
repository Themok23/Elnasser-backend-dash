@extends('layouts.admin.app')

@section('title', 'Mission Submission')

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="page-header-title mb-0">Submission #{{ $submission->id }}</h2>
        <a href="{{ route('admin.missions.submissions.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h4 class="mb-3">Details</h4>
                    <p><strong>Mission:</strong> #{{ $submission->mission_id }} - {{ $submission->mission?->name }}</p>
                    <p><strong>User:</strong> {{ $submission->user?->f_name }} {{ $submission->user?->l_name }} ({{ $submission->user?->phone }})</p>
                    <p><strong>Status:</strong> {{ ucfirst($submission->status) }}</p>
                    <p><strong>Submitted at:</strong> {{ $submission->created_at?->format('Y-m-d H:i') }}</p>
                    <p><strong>User note:</strong><br>{{ $submission->note_user ?? '-' }}</p>
                    <p><strong>Admin note:</strong><br>{{ $submission->note_admin ?? '-' }}</p>
                    @if($submission->status === 'approved')
                        <p><strong>Approved points:</strong> {{ $submission->approved_points ?? $submission->mission?->points }}</p>
                        <p><strong>Awarded at:</strong> {{ $submission->awarded_at?->format('Y-m-d H:i') ?? '-' }}</p>
                    @endif
                </div>
            </div>

            @if($submission->status === 'pending')
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-3">Review</h4>

                        <form method="POST" action="{{ route('admin.missions.submissions.approve', $submission->id) }}" class="mb-3">
                            @csrf
                            <div class="form-group">
                                <label>Approved points (optional)</label>
                                <input type="number" name="approved_points" class="form-control" min="0" placeholder="Default: mission points ({{ $submission->mission?->points }})">
                            </div>
                            <div class="form-group">
                                <label>Admin note (optional)</label>
                                <textarea name="note_admin" class="form-control" rows="3"></textarea>
                            </div>
                            <button class="btn btn-success" type="submit" onclick="return confirm('Approve and award points?')">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('admin.missions.submissions.reject', $submission->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>Rejection reason (required)</label>
                                <textarea name="note_admin" class="form-control" rows="3" required></textarea>
                            </div>
                            <button class="btn btn-danger" type="submit" onclick="return confirm('Reject submission?')">Reject</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Proof Screenshot</h4>
                    @if($submission->proof_image_url)
                        <a href="{{ $submission->proof_image_url }}" target="_blank">
                            <img src="{{ $submission->proof_image_url }}" class="img-fluid" alt="Proof">
                        </a>
                    @else
                        <p class="text-muted">No proof uploaded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection




