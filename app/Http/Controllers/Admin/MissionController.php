<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function index(Request $request)
    {
        $missions = Mission::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('id')
            ->paginate(config('default_pagination', 15));

        return view('admin-views.missions.index', compact('missions'));
    }

    public function create()
    {
        return view('admin-views.missions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'requires_proof' => 'nullable|boolean',
            'proof_instructions' => 'nullable|string',
            'max_per_user' => 'required|integer|min:1',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['requires_proof'] = (bool) ($request->input('requires_proof', 0));
        $data['created_by_admin_id'] = auth('admin')->id();

        Mission::create($data);
        Toastr::success('Mission created successfully');

        return redirect()->route('admin.missions.index');
    }

    public function edit($id)
    {
        $mission = Mission::findOrFail($id);
        return view('admin-views.missions.edit', compact('mission'));
    }

    public function update(Request $request, $id)
    {
        $mission = Mission::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'requires_proof' => 'nullable|boolean',
            'proof_instructions' => 'nullable|string',
            'max_per_user' => 'required|integer|min:1',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['requires_proof'] = (bool) ($request->input('requires_proof', 0));

        $mission->update($data);
        Toastr::success('Mission updated successfully');

        return redirect()->route('admin.missions.index');
    }

    public function destroy($id)
    {
        $mission = Mission::findOrFail($id);
        $mission->delete();
        Toastr::success('Mission deleted successfully');
        return back();
    }

    public function toggleStatus($id)
    {
        $mission = Mission::findOrFail($id);
        $mission->is_active = !$mission->is_active;
        $mission->save();
        Toastr::success('Mission status updated');
        return back();
    }
}


