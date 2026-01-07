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
        $types = SupportTicketType::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('id')
            ->paginate(config('default_pagination', 15));

        return view('admin-views.support-tickets.types.index', compact('types'));
    }

    public function create()
    {
        return view('admin-views.support-tickets.types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_ticket_types,name',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $data['created_by_admin_id'] = auth('admin')->id();

        SupportTicketType::create($data);
        Toastr::success('Ticket type created successfully');

        return redirect()->route('admin.support-ticket-types.index');
    }

    public function edit($id)
    {
        $type = SupportTicketType::findOrFail($id);
        return view('admin-views.support-tickets.types.edit', compact('type'));
    }

    public function update(Request $request, $id)
    {
        $type = SupportTicketType::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_ticket_types,name,' . $type->id,
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = (bool) ($request->input('is_active', 0));
        $type->update($data);

        Toastr::success('Ticket type updated successfully');
        return redirect()->route('admin.support-ticket-types.index');
    }

    public function destroy($id)
    {
        $type = SupportTicketType::findOrFail($id);
        $type->delete();
        Toastr::success('Ticket type deleted successfully');
        return back();
    }
}


