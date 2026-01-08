@extends('layouts.landing.app')

@section('title', 'Support Ticket - Step 1')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Create Support Ticket</h2>
            <div class="text">Step 1: Choose reason of support contact</div>
        </div>

        <div class="card p-4">
            <form method="POST" action="{{ route('support-tickets.step1.store') }}">
                @csrf

                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label">Reason of support contact</label>
                        <select name="support_ticket_type_id" class="form-control form--control" required>
                            <option value="">Select type</option>
                            @php($parents = $types->whereNull('parent_id'))
                            @foreach($parents as $parent)
                                @php($children = $types->where('parent_id', $parent->id))
                                @if($children->isNotEmpty())
                                    <optgroup label="{{ $parent->name }}">
                                        @foreach($children as $type)
                                            <option value="{{ $type->id }}" {{ (old('support_ticket_type_id', $draft['support_ticket_type_id'] ?? null) == $type->id) ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <option value="{{ $parent->id }}" {{ (old('support_ticket_type_id', $draft['support_ticket_type_id'] ?? null) == $parent->id) ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('support_ticket_type_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="cmn--btn border-0" type="submit">Next</button>
                        <button class="btn btn-outline-secondary" type="submit" form="reset-ticket-draft">Reset</button>
                    </div>
                </div>
            </form>
            <form id="reset-ticket-draft" method="POST" action="{{ route('support-tickets.reset') }}">
                @csrf
            </form>
        </div>
    </div>
</section>
@endsection


