<?php

namespace App\Http\Controllers;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\WahaException;
use App\Services\WhatsappInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappInboxController extends Controller
{
    /** Mensagens carregadas por conversa (as mais recentes). */
    private const MESSAGE_LIMIT = 200;

    public function index(Request $request, WhatsappInbox $inbox): Response
    {
        $session = $inbox->session();
        $search = trim((string) $request->query('busca', ''));
        $active = null;

        if ($session !== null && $request->filled('conversa')) {
            $active = WhatsappConversation::query()
                ->forSession($session)
                ->with('lead:id,company_name,contact_name,request_status,source')
                ->find($request->integer('conversa'));

            if ($active !== null) {
                $inbox->markAsRead($active);
            }
        }

        $conversations = $session === null ? collect() : WhatsappConversation::query()
            ->forSession($session)
            ->search($search)
            ->withCount('unreadMessages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('atendimento/index', [
            'session' => $session,
            'webhookEnabled' => filled(config('services.waha.webhook_hmac_key')),
            'filters' => ['busca' => $search],
            'unreadTotal' => $session === null ? 0 : WhatsappMessage::query()
                ->where('direction', WhatsappMessage::DIRECTION_INBOUND)
                ->whereNull('read_at')
                ->whereHas('conversation', fn ($query) => $query->forSession($session))
                ->count(),
            'conversations' => $conversations->map(fn (WhatsappConversation $conversation) => [
                'id' => $conversation->id,
                'name' => $conversation->displayName(),
                'phone' => $conversation->phone,
                'preview' => $conversation->last_message_preview,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread' => $conversation->unread_messages_count,
                'source_page' => $conversation->source_page,
            ])->values(),
            'active' => $active === null ? null : $this->presentConversation($active),
        ]);
    }

    public function reply(Request $request, WhatsappConversation $conversation, WhatsappInbox $inbox): RedirectResponse
    {
        abort_unless($conversation->session === $inbox->session(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ], [
            'body.required' => 'Escreva a mensagem.',
        ]);

        try {
            $inbox->reply($conversation, trim($data['body']), $request->user());
        } catch (WahaException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        return to_route('atendimento.index', ['conversa' => $conversation->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversation(WhatsappConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->latest('sent_at')
            ->latest('id')
            ->limit(self::MESSAGE_LIMIT)
            ->get()
            ->reverse()
            ->values();

        return [
            'id' => $conversation->id,
            'name' => $conversation->displayName(),
            'push_name' => $conversation->name,
            'phone' => $conversation->phone,
            'chat_id' => $conversation->chat_id,
            'source_page' => $conversation->source_page,
            'first_contact_at' => $conversation->created_at?->toIso8601String(),
            // Só solicitações do site; um vínculo antigo com prospect do CRM não aparece aqui.
            'lead' => $conversation->lead?->isSiteRequest() !== true ? null : [
                'id' => $conversation->lead->id,
                'company_name' => $conversation->lead->company_name,
                'contact_name' => $conversation->lead->contact_name,
                'request_status' => $conversation->lead->request_status,
            ],
            'messages' => $messages->map(fn (WhatsappMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'body' => $message->body,
                'has_media' => $message->has_media,
                'status' => $message->status,
                'sent_at' => $message->sent_at->toIso8601String(),
                'sender' => $message->sender?->name,
            ]),
        ];
    }
}
