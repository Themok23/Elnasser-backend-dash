@extends('layouts.admin.app')

@section('title', translate('Edit Story'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/story.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.edit_story') }}
            </span>
        </h1>
        <a href="{{ route('admin.stories.index') }}" class="btn btn-outline-secondary">{{ translate('messages.back') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.stories.update', $story->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>{{ translate('messages.title') }} ({{ translate('messages.optional') }})</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $story->title) }}" placeholder="{{ translate('messages.enter_story_title') }}">
                </div>

                <div class="form-group">
                    <label>{{ translate('messages.type') }} <span class="text-danger">*</span></label>
                    <select name="type" class="form-control" required id="story_type">
                        <option value="image" {{ old('type', $story->type) == 'image' ? 'selected' : '' }}>{{ translate('messages.image') }}</option>
                        <option value="video" {{ old('type', $story->type) == 'video' ? 'selected' : '' }}>{{ translate('messages.video') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>{{ translate('messages.current_media') }}</label>
                    <div class="mb-2">
                        @if($story->type == 'image')
                            <img src="{{ $story->media_full_url }}" alt="Story" style="max-width: 300px; max-height: 300px; border-radius: 4px;">
                        @else
                            <video src="{{ $story->media_full_url }}" controls style="max-width: 300px; max-height: 300px; border-radius: 4px;"></video>
                        @endif
                    </div>
                    <label>{{ translate('messages.change_media') }} ({{ translate('messages.optional') }})</label>
                    <input type="file" name="media" class="form-control" accept="image/*,video/*" id="media_input">
                    <small class="form-text text-muted">
                        {{ translate('messages.allowed_formats') }}: {{ translate('messages.image') }} (jpeg, jpg, png, gif), {{ translate('messages.video') }} (mp4, webm, ogg). {{ translate('messages.max_size') }}: 10MB
                    </small>
                    <div id="media_preview" class="mt-2"></div>
                </div>

                <div class="form-group">
                    <label>{{ translate('messages.description') }} ({{ translate('messages.optional') }})</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="{{ translate('messages.enter_story_description') }}">{{ old('description', $story->description) }}</textarea>
                </div>

                <div class="form-group">
                    <label>{{ translate('messages.expires_at') }} ({{ translate('messages.optional') }})</label>
                    <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at', $story->expires_at ? $story->expires_at->format('Y-m-d\TH:i') : '') }}">
                    <small class="form-text text-muted">{{ translate('messages.story_will_expire_after_this_date') }}</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $story->is_active) ? 'checked' : '' }}>
                        {{ translate('messages.active') }}
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">{{ translate('messages.update') }}</button>
            </form>
        </div>
    </div>
</div>

@push('script_2')
<script>
    document.getElementById('media_input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('media_preview');
        const type = document.getElementById('story_type').value;
        
        preview.innerHTML = '';
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (type === 'image') {
                    preview.innerHTML = '<p class="text-info">{{ translate('messages.new_preview') }}:</p><img src="' + e.target.result + '" style="max-width: 300px; max-height: 300px; border-radius: 4px;">';
                } else {
                    preview.innerHTML = '<p class="text-info">{{ translate('messages.new_preview') }}:</p><video src="' + e.target.result + '" controls style="max-width: 300px; max-height: 300px; border-radius: 4px;"></video>';
                }
            };
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
@endsection

