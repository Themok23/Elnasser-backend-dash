<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\CustomerLogic;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionSubmission;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MissionSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $missions = Mission::orderByDesc('id')->get(['id', 'name']);

        $submissions = MissionSubmission::with(['mission:id,name,points', 'user:id,f_name,l_name,phone'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('mission_id'), fn($q) => $q->where('mission_id', $request->mission_id))
            ->orderByDesc('id')
            ->paginate(config('default_pagination', 15))
            ->appends($request->query());

        return view('admin-views.missions.submissions.index', compact('submissions', 'missions'));
    }

    public function show($id)
    {
        $submission = MissionSubmission::with(['mission', 'user', 'reviewedByAdmin'])
            ->findOrFail($id);

        return view('admin-views.missions.submissions.show', compact('submission'));
    }

    public function approve(Request $request, $id)
    {
        $submission = MissionSubmission::with('mission')->findOrFail($id);

        if ($submission->status !== 'pending') {
            Toastr::error('Submission is not pending');
            return back();
        }
        if ($submission->awarded_at) {
            Toastr::error('Points already awarded for this submission');
            return back();
        }

        $data = $request->validate([
            'approved_points' => 'nullable|integer|min:0',
            'note_admin' => 'nullable|string|max:2000',
        ]);

        $points = $data['approved_points'] ?? $submission->mission->points;

        DB::beginTransaction();
        try {
            $submission->status = 'approved';
            $submission->approved_points = $points;
            $submission->note_admin = $data['note_admin'] ?? $submission->note_admin;
            $submission->reviewed_by_admin_id = auth('admin')->id();
            $submission->reviewed_at = now();

            // award points
            CustomerLogic::create_loyalty_point_transaction(
                $submission->user_id,
                (string) $submission->id,
                (int) $points,
                'mission_reward'
            );

            $submission->awarded_at = now();
            $submission->save();

            DB::commit();
            Toastr::success('Submission approved and points awarded');
            return back();
        } catch (\Throwable $e) {
            DB::rollBack();
            Toastr::error('Failed to approve submission');
            return back();
        }
    }

    public function reject(Request $request, $id)
    {
        $submission = MissionSubmission::findOrFail($id);

        if ($submission->status !== 'pending') {
            Toastr::error('Submission is not pending');
            return back();
        }

        $data = $request->validate([
            'note_admin' => 'required|string|max:2000',
        ]);

        $submission->status = 'rejected';
        $submission->note_admin = $data['note_admin'];
        $submission->reviewed_by_admin_id = auth('admin')->id();
        $submission->reviewed_at = now();
        $submission->save();

        Toastr::success('Submission rejected');
        return back();
    }
}




