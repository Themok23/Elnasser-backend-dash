<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get all active stories
            $stories = Story::active()
                ->orderByDesc('created_at')
                ->get();

            // Get viewed story IDs for this user
            $viewedStoryIds = StoryView::where('user_id', $user->id)
                ->pluck('story_id')
                ->toArray();

            // Format stories with view status
            $formattedStories = $stories->map(function ($story) use ($viewedStoryIds, $user) {
                $isViewed = in_array($story->id, $viewedStoryIds);
                
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'type' => $story->type,
                    'media_url' => $story->media_full_url,
                    'description' => $story->description,
                    'is_active' => $story->is_active,
                    'expires_at' => $story->expires_at?->toISOString(),
                    'created_at' => $story->created_at->toISOString(),
                    'is_viewed' => $isViewed,
                ];
            });

            return response()->json([
                'stories' => $formattedStories,
                'total' => $formattedStories->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [['code' => 'stories', 'message' => 'Failed to fetch stories']]
            ], 500);
        }
    }

    public function markAsViewed(Request $request, $id)
    {
        try {
            $user = $request->user();
            $story = Story::findOrFail($id);

            // Check if already viewed
            $existingView = StoryView::where('story_id', $story->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingView) {
                StoryView::create([
                    'story_id' => $story->id,
                    'user_id' => $user->id,
                    'viewed_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Story marked as viewed',
                'story_id' => $story->id,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [['code' => 'story_view', 'message' => 'Failed to mark story as viewed']]
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $story = Story::active()->findOrFail($id);

            // Check if viewed
            $isViewed = StoryView::where('story_id', $story->id)
                ->where('user_id', $user->id)
                ->exists();

            return response()->json([
                'id' => $story->id,
                'title' => $story->title,
                'type' => $story->type,
                'media_url' => $story->media_full_url,
                'description' => $story->description,
                'is_active' => $story->is_active,
                'expires_at' => $story->expires_at?->toISOString(),
                'created_at' => $story->created_at->toISOString(),
                'is_viewed' => $isViewed,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [['code' => 'story', 'message' => 'Story not found']]
            ], 404);
        }
    }
}

