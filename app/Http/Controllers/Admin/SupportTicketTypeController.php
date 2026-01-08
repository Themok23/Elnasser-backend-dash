<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicketType;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class SupportTicketTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicketType::query()->with('parent');
        
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $allTypes = $query->orderByDesc('id')->get();
        
        // Separate parents and children
        $parents = $allTypes->whereNull('parent_id');
        $children = $allTypes->whereNotNull('parent_id');

        return view('admin-views.support-tickets.types.index', compact('parents', 'children'));
    }

    public function create()
    {
        $parents = SupportTicketType::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('admin-views.support-tickets.types.create', compact('parents'));
    }

    public function createParent()
    {
        return view('admin-views.support-tickets.types.create-parent');
    }

    public function storeParent(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_ticket_types,name',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['created_by_admin_id'] = auth('admin')->id();
        $data['parent_id'] = null; // Explicitly set to null for parent

        SupportTicketType::create($data);
        Toastr::success('Parent ticket type created successfully');

        return redirect()->route('admin.support-ticket-types.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_id' => 'required|integer|exists:support_ticket_types,id',
            'name' => 'required|string|max:255|unique:support_ticket_types,name',
            'is_active' => 'nullable|boolean',
        ]);

        // Only allow one level: parent_id must reference a top-level parent
        $parent = SupportTicketType::query()->find($data['parent_id']);
        if ($parent && $parent->parent_id !== null) {
            return back()->withErrors(['parent_id' => 'Parent must be a top-level type.'])->withInput();
        }

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['created_by_admin_id'] = auth('admin')->id();

        SupportTicketType::create($data);
        Toastr::success('Ticket type created successfully');

        return redirect()->route('admin.support-ticket-types.index');
    }

    public function edit($id)
    {
        $type = SupportTicketType::findOrFail($id);

        $parents = SupportTicketType::query()
            ->whereNull('parent_id')
            ->where('id', '!=', $type->id)
            ->orderBy('name')
            ->get();

        return view('admin-views.support-tickets.types.edit', compact('type', 'parents'));
    }

    public function update(Request $request, $id)
    {
        $type = SupportTicketType::findOrFail($id);

        // If editing a parent, force parent_id to null
        if ($type->parent_id === null) {
            $data = $request->validate([
                'name' => 'required|string|max:255|unique:support_ticket_types,name,' . $type->id,
                'is_active' => 'nullable|boolean',
            ]);
            $data['parent_id'] = null; // Force null for parents
        } else {
            // Editing a child - parent_id is required
            $data = $request->validate([
                'parent_id' => 'required|integer|exists:support_ticket_types,id|not_in:' . $type->id,
                'name' => 'required|string|max:255|unique:support_ticket_types,name,' . $type->id,
                'is_active' => 'nullable|boolean',
            ]);

            // Only allow one level: parent_id must reference a top-level parent
            $parent = SupportTicketType::query()->find($data['parent_id']);
            if ($parent && $parent->parent_id !== null) {
                return back()->withErrors(['parent_id' => 'Parent must be a top-level type.'])->withInput();
            }
        }

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $type->update($data);

        Toastr::success('Ticket type updated successfully');
        return redirect()->route('admin.support-ticket-types.index');
    }

    public function editParent($id)
    {
        $type = SupportTicketType::findOrFail($id);

        // Ensure it's a parent
        if ($type->parent_id !== null) {
            Toastr::error('This is not a parent type.');
            return redirect()->route('admin.support-ticket-types.index');
        }

        return view('admin-views.support-tickets.types.edit-parent', compact('type'));
    }

    public function updateParent(Request $request, $id)
    {
        $type = SupportTicketType::findOrFail($id);

        // Ensure it's a parent
        if ($type->parent_id !== null) {
            Toastr::error('This is not a parent type.');
            return redirect()->route('admin.support-ticket-types.index');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_ticket_types,name,' . $type->id,
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['parent_id'] = null; // Force null for parents

        $type->update($data);
        Toastr::success('Parent ticket type updated successfully');

        return redirect()->route('admin.support-ticket-types.index');
    }

    public function destroyParent($id)
    {
        $type = SupportTicketType::findOrFail($id);

        // Ensure it's a parent
        if ($type->parent_id !== null) {
            Toastr::error('This is not a parent type.');
            return redirect()->route('admin.support-ticket-types.index');
        }

        if ($type->children()->exists()) {
            Toastr::error('Cannot delete a parent type that has children. Delete or move children first.');
            return back();
        }

        $type->delete();
        Toastr::success('Parent ticket type deleted successfully');
        return redirect()->route('admin.support-ticket-types.index');
    }

    public function destroy($id)
    {
        $type = SupportTicketType::findOrFail($id);

        // If it's a parent, redirect to parent delete method
        if ($type->parent_id === null) {
            return $this->destroyParent($id);
        }

        $type->delete();
        Toastr::success('Ticket type deleted successfully');
        return back();
    }
}


