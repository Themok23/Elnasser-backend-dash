@extends('layouts.admin.app')

@section('title', translate('Stories'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/story.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.stories') }}
            </span>
        </h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.stories.create') }}" class="btn btn-primary">{{ translate('messages.add_new_story') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="{{ translate('messages.search_by_title') }}" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" type="submit">{{ translate('messages.search') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.id') }}</th>
                            <th>{{ translate('messages.media') }}</th>
                            <th>{{ translate('messages.title') }}</th>
                            <th>{{ translate('messages.type') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th>{{ translate('messages.expires_at') }}</th>
                            <th>{{ translate('messages.created_at') }}</th>
                            <th class="text-right">{{ translate('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stories as $story)
                            <tr>
                                <td>{{ $story->id }}</td>
                                <td>
                                    @if($story->type == 'image')
                                        <img src="{{ $story->media_full_url }}" alt="Story" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    @else
                                        <video style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;" muted>
                                            <source src="{{ $story->media_full_url }}" type="video/mp4">
                                        </video>
                                    @endif
                                </td>
                                <td>{{ $story->title ?? translate('messages.no_title') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $story->type == 'image' ? 'info' : 'warning' }}">
                                        {{ ucfirst($story->type) }}
                                    </span>
                                </td>
                                <td>
                                    @if($story->is_active)
                                        <span class="badge badge-soft-success">{{ translate('messages.active') }}</span>
                                    @else
                                        <span class="badge badge-soft-danger">{{ translate('messages.inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ $story->expires_at ? $story->expires_at->format('Y-m-d H:i') : translate('messages.no_expiry') }}</td>
                                <td>{{ $story->created_at->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.stories.edit', $story->id) }}">{{ translate('messages.edit') }}</a>
                                    <a class="btn btn-sm btn-outline-warning" href="{{ route('admin.stories.toggle-status', $story->id) }}">
                                        {{ $story->is_active ? translate('messages.disable') : translate('messages.enable') }}
                                    </a>
                                    <form action="{{ route('admin.stories.destroy', $story->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('{{ translate('messages.are_you_sure_delete') }}')">{{ translate('messages.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">{{ translate('messages.no_stories_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {!! $stories->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection

