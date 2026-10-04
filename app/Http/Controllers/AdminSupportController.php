<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSupportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $requests = SupportRequest::query()
            ->with(['user', 'shop', 'resolvedBy'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support.index', compact('requests', 'status'));
    }

    public function show(SupportRequest $support): View
    {
        $support->loadMissing(['user', 'shop', 'resolvedBy']);

        return view('admin.support.show', compact('support'));
    }

    public function resolve(Request $request, SupportRequest $support): RedirectResponse
    {
        $validated = $request->validate(['resolution_notes' => ['nullable', 'string', 'max:2000']]);
        $support->update([
            'status' => 'resolved',
            'resolution_notes' => $validated['resolution_notes'] ?? null,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        return to_route('admin.support.index')->with('status', 'Solicitud de soporte marcada como resuelta.');
    }
}
