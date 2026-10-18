<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    /**
     * The chat page with an empty, new conversation.
     */
    public function index(Request $request): Response
    {
        return $this->page($request, null);
    }

    /**
     * The chat page showing one of your conversations.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        return $this->page($request, $conversation);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        Gate::authorize('delete', $conversation);

        $conversation->delete(); // messages are removed by the database (cascade)

        return to_route('chat.index');
    }

    /**
     * Both URLs render the same page; only the open conversation differs.
     */
    private function page(Request $request, ?Conversation $conversation): Response
    {
        $user = $request->user();
        $team = Team::findOrFail($user->current_team_id);

        Gate::authorize('create', [Conversation::class, $team]); // must be a member

        $conversations = Conversation::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (Conversation $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'updatedAt' => $item->updated_at->diffForHumans(),
            ]);

        return Inertia::render('chat/Index', [
            'conversations' => $conversations,
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ] : null,
            'messages' => $conversation
                ? $conversation->messages()->get()->map(fn (ConversationMessage $message): array => $message->toChatArray())
                : [],
            'maxQuestionLength' => SendMessageRequest::MAX_LENGTH,
        ]);
    }
}
