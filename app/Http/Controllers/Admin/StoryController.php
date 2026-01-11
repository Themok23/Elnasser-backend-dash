<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        $stories = Story::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('id')
            ->paginate(config('default_pagination', 15));

        return view('admin-views.stories.index', compact('stories'));
    }

    public function create()
    {
        return view('admin-views.stories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'required|in:image,video',
            'media' => 'required|file|mimes:jpeg,jpg,png,gif,mp4,webm,ogg|max:10240',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $data = [
            'title' => $request->title,
            'type' => $request->type,
            'description' => $request->description,
            'is_active' => (bool) ($request->input('is_active', 1)),
            'expires_at' => $request->expires_at ? \Carbon\Carbon::parse($request->expires_at) : null,
            'created_by_admin_id' => auth('admin')->id(),
        ];

        // Handle file upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $format = strtolower($file->getClientOriginalExtension());
            $dir = 'story/';
            $data['media_url'] = Helpers::upload($dir, $format, $file);
        }

        Story::create($data);
        Toastr::success(translate('messages.story_created_successfully'));

        return redirect()->route('admin.stories.index');
    }

    public function edit($id)
    {
        $story = Story::findOrFail($id);
        return view('admin-views.stories.edit', compact('story'));
    }

    public function update(Request $request, $id)
    {
        $story = Story::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'required|in:image,video',
            'media' => 'nullable|file|mimes:jpeg,jpg,png,gif,mp4,webm,ogg|max:10240',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $data = [
            'title' => $request->title,
            'type' => $request->type,
            'description' => $request->description,
            'is_active' => (bool) ($request->input('is_active', 1)),
            'expires_at' => $request->expires_at ? \Carbon\Carbon::parse($request->expires_at) : null,
        ];

        // Handle file upload if new file is provided
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $format = strtolower($file->getClientOriginalExtension());
            $dir = 'story/';
            $data['media_url'] = Helpers::update($dir, $story->media_url, $format, $file);
        }

        $story->update($data);
        Toastr::success(translate('messages.story_updated_successfully'));

        return redirect()->route('admin.stories.index');
    }

    public function destroy($id)
    {
        $story = Story::findOrFail($id);
        $story->delete();
        Toastr::success(translate('messages.story_deleted_successfully'));
        return back();
    }

    public function toggleStatus($id)
    {
        $story = Story::findOrFail($id);
        $story->is_active = !$story->is_active;
        $story->save();
        Toastr::success(translate('messages.story_status_updated'));
        return back();
    }
}

