@extends('layouts.landing.app')

@section('title', 'Support Ticket - Review')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Review Ticket</h2>
            <div class="text">Please confirm the details before submitting</div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ route('support-tickets.step3') }}" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="card p-4">
            <div class="mb-3">
                <h5 class="mb-2">Type</h5>
                <div><strong>Reason:</strong> {{ $type?->name }}</div>
                <div><strong>Inquiry:</strong> {{ $inquiryTypes[$draft['inquiry_type']] ?? $draft['inquiry_type'] }}</div>
            </div>

            <div class="mb-3">
                <h5 class="mb-2">Branch</h5>
                <div>{{ $branch?->name ?? '-' }}</div>
            </div>

            <div class="mb-3">
                <h5 class="mb-2">Contact</h5>
                <div><strong>Email:</strong> {{ $draft['contact_email'] }}</div>
                <div><strong>Phone:</strong> {{ $draft['contact_phone'] }}</div>
                <div><strong>Callback:</strong> {{ !empty($draft['callback_requested']) ? 'Yes' : 'No' }}</div>
                @if(!empty($draft['callback_requested']))
                    <div><strong>Callback time:</strong> {{ $draft['callback_time'] ?? '-' }}</div>
                    <div><strong>Callback notes:</strong> {{ $draft['callback_notes'] ?? '-' }}</div>
                @endif
            </div>

            <div class="mb-4">
                <h5 class="mb-2">Problem description</h5>
                <div class="p-3 rounded" style="background:#f6f6f6;">
                    {!! nl2br(e($draft['problem'])) !!}
                </div>
            </div>

            <form method="POST" action="{{ route('support-tickets.submit') }}">
                @csrf
                <input type="hidden" name="confirm" value="1">
                <button class="cmn--btn border-0" type="submit">Submit Ticket</button>
            </form>
        </div>
    </div>
</section>
@endsection


