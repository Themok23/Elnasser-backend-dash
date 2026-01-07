@extends('layouts.landing.app')

@section('title', 'Support Ticket Submitted')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Ticket Submitted</h2>
            <div class="text">Your support ticket has been created successfully</div>
        </div>

        <div class="card p-4">
            <p class="mb-2"><strong>Ticket #:</strong> {{ $ticket->ticket_number }}</p>
            <p class="mb-2"><strong>Status:</strong> {{ $ticket->status }}</p>
            <p class="mb-0">Keep your ticket number to check status later via the mobile app / API.</p>

            <div class="mt-4 d-flex gap-2">
                <a class="cmn--btn border-0" href="{{ route('support-tickets.step1') }}">Create another ticket</a>
                <a class="btn btn-outline-secondary" href="{{ route('home') }}">Home</a>
            </div>
        </div>
    </div>
</section>
@endsection


