@extends('layouts.landing.app')

@section('title', 'Support Ticket - Step 1')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Create Support Ticket</h2>
            <div class="text">Step 1: Choose reason and inquiry type</div>
        </div>

        <div class="card p-4">
            <form method="POST" action="{{ route('support-tickets.step1.store') }}">
                @csrf

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Reason of support contact</label>
                        <select name="support_ticket_type_id" class="form-control form--control" required>
                            <option value="">Select type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ (old('support_ticket_type_id', $draft['support_ticket_type_id'] ?? null) == $type->id) ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('support_ticket_type_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Inquiry type</label>
                        <select name="inquiry_type" class="form-control form--control" required>
                            <option value="">Select inquiry</option>
                            @foreach($inquiryTypes as $value => $label)
                                <option value="{{ $value }}" {{ (old('inquiry_type', $draft['inquiry_type'] ?? null) === $value) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('inquiry_type')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="cmn--btn border-0" type="submit">Next</button>
                        <form method="POST" action="{{ route('support-tickets.reset') }}">
                            @csrf
                            <button class="btn btn-outline-secondary" type="submit">Reset</button>
                        </form>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection


