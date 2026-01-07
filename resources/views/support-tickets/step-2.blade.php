@extends('layouts.landing.app')

@section('title', 'Support Ticket - Step 2')

@section('content')
<section class="about-section py-5 position-relative">
    <div class="container">
        <div class="section-header">
            <h2 class="title mb-2">Create Support Ticket</h2>
            <div class="text">Step 2: Choose store/branch</div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ route('support-tickets.step1') }}" class="btn btn-outline-secondary">Back</a>
            <form method="GET" class="d-flex gap-2" style="max-width:420px; width:100%">
                <input type="text" class="form-control form--control" name="search" placeholder="Search branch name" value="{{ request('search') }}">
                <button class="btn btn-secondary" type="submit">Search</button>
            </form>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Branch</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $branch->name }}</div>
                                    @if($branch->description)
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($branch->description, 120) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('support-tickets.step2.store') }}">
                                        @csrf
                                        <input type="hidden" name="branch_location_id" value="{{ $branch->id }}">
                                        <button class="btn btn-primary" type="submit">Select</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center">No branches found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {!! $branches->links() !!}
            </div>
        </div>
    </div>
</section>
@endsection


