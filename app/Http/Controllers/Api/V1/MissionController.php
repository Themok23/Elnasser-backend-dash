<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MissionController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $missions = Mission::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('id')
            ->get();

        $missionIds = $missions->pluck('id')->all();

        $submissions = MissionSubmission::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('mission_id', $missionIds)
            ->latest()
            ->get()
            ->groupBy('mission_id');

        $data = $missions->map(function (Mission $mission) use ($submissions) {
            $mine = $submissions->get($mission->id, collect());

            $approvedCount = (int) $mine->where('status', 'approved')->count();
            $hasPending = $mine->where('status', 'pending')->isNotEmpty();
            $last = $mine->first();

            $canSubmit = !$hasPending && ($approvedCount < max(1, (int) $mission->max_per_user));

            return [
                'id' => $mission->id,
                'name' => $mission->name,
                'description' => $mission->description,
                'points' => (int) $mission->points,
                'requires_proof' => (bool) $mission->requires_proof,
                'proof_instructions' => $mission->proof_instructions,
                'max_per_user' => (int) $mission->max_per_user,
                'start_at' => $mission->start_at?->toISOString(),
                'end_at' => $mission->end_at?->toISOString(),
                'my' => [
                    'can_submit' => $canSubmit,
                    'approved_count' => $approvedCount,
                    'has_pending' => $hasPending,
                    'last_status' => $last?->status,
                    'last_submission_id' => $last?->id,
                ],
            ];
        })->values();

        return response()->json(['missions' => $data], 200);
    }

    public function submit(Request $request, $missionId)
    {
        $mission = Mission::findOrFail($missionId);
        $now = now();

        if (!$mission->is_active) {
            return response()->json(['errors' => [['code' => 'mission', 'message' => 'Mission is not active']]], 403);
        }
        if ($mission->start_at && $mission->start_at->gt($now)) {
            return response()->json(['errors' => [['code' => 'mission', 'message' => 'Mission not started yet']]], 403);
        }
        if ($mission->end_at && $mission->end_at->lt($now)) {
            return response()->json(['errors' => [['code' => 'mission', 'message' => 'Mission has ended']]], 403);
        }

        $rules = [
            'note' => 'nullable|string|max:1000',
        ];
        if ($mission->requires_proof) {
            $rules['proof_image'] = 'required|file|mimes:jpg,jpeg,png,webp|max:2048';
        } else {
            $rules['proof_image'] = 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $request->user()->id;

        $approvedCount = MissionSubmission::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->count();

        if ($approvedCount >= max(1, (int) $mission->max_per_user)) {
            return response()->json(['errors' => [['code' => 'mission', 'message' => 'Mission submission limit reached']]], 403);
        }

        $hasPending = MissionSubmission::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json(['errors' => [['code' => 'mission', 'message' => 'You already have a pending submission for this mission']]], 403);
        }

        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = Storage::disk('public')->putFile('missions/proofs', $request->file('proof_image'));
        }

        $submission = MissionSubmission::create([
            'mission_id' => $mission->id,
            'user_id' => $userId,
            'status' => 'pending',
            'proof_image_path' => $proofPath,
            'note_user' => $request->input('note'),
        ]);

        return response()->json([
            'message' => 'Submitted successfully',
            'submission' => $submission->fresh(),
        ], 200);
    }

    public function mySubmissions(Request $request)
    {
        $submissions = MissionSubmission::with('mission:id,name,points')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($submissions, 200);
    }
}



