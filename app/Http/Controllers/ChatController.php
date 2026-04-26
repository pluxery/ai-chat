<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    /**
     * Display the chat page.
     */
    public function index()
    {
        $chats = Chat::where('user_id', Auth::id())
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Chat', [
            'chats' => $chats->map(fn($chat) => [
                'id' => $chat->id,
                'title' => $chat->title ?? 'New Chat',
                'message_count' => $chat->messages_count,
                'updated_at' => $chat->updated_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function show(string $chatId)
    {
        $chat = Chat::where('id', $chatId)
            ->where('user_id', Auth::id())
            ->with('messages')
            ->firstOrFail();

        return response()->json([
            'messages' => $chat->messages->map(fn($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Send a message and get AI response.
     */
    public function send(Request $request)
    {

        $data = $request->json()->all();


        $user = Auth::user();

        // Find or create chat
        if ($data['conversation_id']) {
            $chat = Chat::where('id', $data['conversation_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $chat = Chat::create([
                'user_id' => $user->id,
                'title' => substr($data['message'], 0, 50),
            ]);
        }

        // Save user message
        Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        // Generate AI response
        $response = $this->generateAiResponse($data['message']);

        // Save AI response
        Message::create([
            'chat_id' => $chat->id,
            'role' => 'assistant',
            'content' => $response,
        ]);

        return response()->json([
            'conversation_id' => $chat->id,
            'response' => $response,
        ]);
    }

    /**
     * Generate AI response (replace with actual AI integration).
     */
    private function generateAiResponse(string $message): string
    {
        // TODO: Integrate with actual AI service (OpenAI, DeepSeek, etc.)

        return 'Это сложный вопрос, я не знаю. ';
    }
}
