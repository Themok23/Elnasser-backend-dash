@extends('layouts.landing.app')

@section('title', 'Support Ticket - Step 3')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Create Support Ticket</h2>
            <div class="text">Step 3: Describe the problem & confirm contact details</div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ route('support-tickets.step2') }}" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="card p-4">
            <form method="POST" action="{{ route('support-tickets.step3.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="form-label">Problem description</label>
                    <textarea name="problem" class="form-control form--control" rows="5" required placeholder="Write the problem you are facing">{{ old('problem', $draft['problem'] ?? '') }}</textarea>
                    @error('problem')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">Attach Images (optional)</label>
                    <input type="file" name="images[]" class="form-control form--control" multiple accept="image/*">
                    <small class="form-text text-muted">You can select multiple images. Max 5MB per image.</small>
                    @error('images.*')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="contact_email" class="form-control form--control" required value="{{ old('contact_email', $draft['contact_email'] ?? '') }}">
                        @error('contact_email')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="contact_phone" class="form-control form--control" required value="{{ old('contact_phone', $draft['contact_phone'] ?? '') }}">
                        @error('contact_phone')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="d-flex align-items-center gap-2">
                        <input type="checkbox" name="callback_requested" value="1" {{ old('callback_requested', $draft['callback_requested'] ?? false) ? 'checked' : '' }}>
                        Callback requested (default: No)
                    </label>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Callback time (required if callback requested)</label>
                        <input type="datetime-local" name="callback_time" class="form-control form--control" value="{{ old('callback_time', $draft['callback_time'] ?? '') }}">
                        @error('callback_time')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Callback notes (optional)</label>
                        <input type="text" name="callback_notes" class="form-control form--control" value="{{ old('callback_notes', $draft['callback_notes'] ?? '') }}" placeholder="Best time to call, extra notes...">
                        @error('callback_notes')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <button class="cmn--btn border-0" type="submit">Review</button>
            </form>
        </div>
    </div>
</section>
@endsection


