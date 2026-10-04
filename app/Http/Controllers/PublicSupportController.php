<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Services\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSupportController extends Controller
{
    public function create(): View
    {
        return view('support.create');
    }

    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        $turnstile->validate($request->input('cf-turnstile-response'), $request, 'support');
        $validated = $request->validate([
            'category' => ['required', Rule::in(['problem', 'suggestion'])],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);
        $user = $request->user();
        $shop = $user?->shops()->oldest('id')->first();

        SupportRequest::create([
            'user_id' => $user?->id,
            'shop_id' => $shop?->id,
            'requester_type' => $user ? ($shop ? 'catalog_owner' : 'registered_user') : 'visitor',
            'category' => $validated['category'],
            'requester_name' => $user?->name ?? $validated['name'],
            'requester_email' => $user?->email ?? strtolower(trim($validated['email'])),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);

        return to_route('support.create')->with('status', 'Recibimos tu mensaje. Nuestro equipo lo revisará pronto.');
    }
}
