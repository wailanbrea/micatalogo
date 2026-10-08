<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isOwner = $this->isSupportOwner($user);
        $query = SupportConversation::query()
            ->with([
                'shop:id,public_id,name',
                'requester:id,name,email',
                'assignedTo:id,name,email',
            ])
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)])
            // La cola se procesa por la llegada de la solicitud original.
            // Una respuesta posterior no debe moverla ni alterar su prioridad.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($isOwner) {
            $query->where('assigned_to_id', $user->id);
        } else {
            $query->where('requester_id', $user->id);
        }

        return response()->json([
            'owner_email' => config('support.owner_email'),
            'is_inbox' => $isOwner,
            'conversations' => $query->limit(100)->get()
                ->map(fn (SupportConversation $conversation) => $this->conversationPayload($conversation))
                ->values(),
        ]);
    }

    public function store(Request $request, Shop $shop): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isSupportOwner($user) || $user->canSellAtShop($shop), 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
        ]);
        $owner = $this->supportOwner();

        $conversation = DB::transaction(function () use ($shop, $user, $owner, $data): SupportConversation {
            $conversation = SupportConversation::create([
                'shop_id' => $shop->id,
                'requester_id' => $user->id,
                'assigned_to_id' => $owner->id,
                'subject' => trim($data['subject'] ?? '') ?: 'Ayuda con MiCatalogo',
                'status' => 'open',
                'last_message_at' => now(),
            ]);
            SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => trim($data['message']),
            ]);

            return $conversation;
        });

        $conversation->load([
            'shop:id,public_id,name',
            'requester:id,name,email',
            'assignedTo:id,name,email',
            'messages.sender:id,name,email',
        ]);

        return response()->json($this->conversationDetailPayload($conversation), 201);
    }

    public function show(Request $request, SupportConversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);
        $conversation->load([
            'shop:id,public_id,name',
            'requester:id,name,email',
            'assignedTo:id,name,email',
            'messages.sender:id,name,email',
        ]);
        $this->markUnreadAsRead($conversation, $user);
        $conversation->load('messages.sender:id,name,email');

        return response()->json($this->conversationDetailPayload($conversation));
    }

    public function storeMessage(Request $request, SupportConversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:4000']]);

        $message = DB::transaction(function () use ($conversation, $user, $data): SupportMessage {
            $message = SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => trim($data['message']),
            ]);
            $conversation->update(['status' => 'open', 'last_message_at' => now()]);

            return $message;
        });
        $message->load('sender:id,name,email');

        return response()->json([
            'message' => 'Mensaje enviado.',
            'chat_message' => $this->messagePayload($message),
        ], 201);
    }

    public function markRead(Request $request, SupportConversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($conversation, $user);
        $this->markUnreadAsRead($conversation, $user);

        return response()->json(['message' => 'Mensajes marcados como leídos.']);
    }

    private function supportOwner(): User
    {
        $owner = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) config('support.owner_email'))])
            ->first();
        abort_if(! $owner, 503, 'El buzón de soporte todavía no está configurado.');

        return $owner;
    }

    private function isSupportOwner(User $user): bool
    {
        return strtolower((string) $user->email) === strtolower((string) config('support.owner_email'));
    }

    private function authorizeConversation(SupportConversation $conversation, User $user): void
    {
        abort_unless(
            $conversation->requester_id === $user->id
                || ($conversation->assigned_to_id === $user->id && $this->isSupportOwner($user)),
            404
        );
    }

    private function markUnreadAsRead(SupportConversation $conversation, User $user): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);
    }

    private function conversationPayload(SupportConversation $conversation): array
    {
        return [
            'id' => $conversation->public_id,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'last_message_at' => ($conversation->last_message_at ?: $conversation->created_at)?->toIso8601String(),
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'shop' => $conversation->shop ? [
                'id' => $conversation->shop->public_id,
                'name' => $conversation->shop->name,
            ] : null,
            'requester' => $conversation->requester ? [
                'id' => (string) $conversation->requester->id,
                'name' => $conversation->requester->name,
                'email' => $conversation->requester->email,
            ] : null,
            'assigned_to' => $conversation->assignedTo ? [
                'id' => (string) $conversation->assignedTo->id,
                'name' => $conversation->assignedTo->name,
                'email' => $conversation->assignedTo->email,
            ] : null,
        ];
    }

    private function conversationDetailPayload(SupportConversation $conversation): array
    {
        return [
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $conversation->messages
                ->map(fn (SupportMessage $message) => $this->messagePayload($message))
                ->values(),
        ];
    }

    private function messagePayload(SupportMessage $message): array
    {
        return [
            'id' => $message->public_id,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'sender' => $message->sender ? [
                'id' => (string) $message->sender->id,
                'name' => $message->sender->name,
                'email' => $message->sender->email,
            ] : null,
        ];
    }
}
