<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\ConsultationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ChatResource;
use App\Http\Resources\ChatMessageResource;

class ChatController extends Controller
{
    /**
     * Get all chats for the authenticated user.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $chats = Chat::query()
            ->where(function ($query) use ($user) {
                $query->where('client_id', $user->id)
                    ->orWhere('lawyer_id', $user->id);
            })
            ->with(['client', 'lawyer'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $chats->map(function ($chat) {
                return [
                    'id' => $chat->id,
                    'client' => [
                        'id' => $chat->client->id,
                        'name' => $chat->client->name,
                    ],
                    'lawyer' => [
                        'id' => $chat->lawyer->id,
                        'name' => $chat->lawyer->name,
                    ],
                    'status' => $chat->status,
                    'last_message_at' => $chat->last_message_at?->format('Y-m-d H:i:s'),
                    'created_at' => $chat->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Get or create a chat for a paid consultation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrCreateConsultationChat(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'consultation_request_id' => 'required|exists:consultation_requests,id',
        ]);

        $consultationRequest = ConsultationRequest::with(['client.user', 'lawyer.user', 'payment'])
            ->findOrFail($validated['consultation_request_id']);

        // Get the client and lawyer user IDs
        $clientUserId = $consultationRequest->client->user_id;
        $lawyerUserId = $consultationRequest->lawyer->user_id;

        // Check if the user is either the client or the lawyer
        if ($user->id !== $clientUserId && $user->id !== $lawyerUserId) {
            return $this->forbiddenResponse('You are not authorized to access this chat');
        }

        // Check if the consultation is paid
        if (!$consultationRequest->isPaid()) {
            return $this->paymentRequiredResponse('This consultation requires payment before starting the chat');
        }

        try {
            // Get existing chat or create a new one
            $chat = Chat::firstOrCreate(
                [
                    'client_id' => $clientUserId,
                    'lawyer_id' => $lawyerUserId,
                    'consultation_request_id' => $consultationRequest->id,
                ],
                [
                    'status' => Chat::STATUS_ACTIVE,
                    'last_message_at' => now(),
                ]
            );

            // If this is a new chat, create a system message
            if ($chat->wasRecentlyCreated) {
                $this->createSystemMessage($chat, 'Chat started for paid consultation');
            }

            return response()->json([
                'success' => true,
                'message' => 'Chat session ready',
                'data' => [
                    'chat_id' => $chat->id,
                    'client' => [
                        'id' => $consultationRequest->client->client_id,
                        'user_id' => $clientUserId,
                        'name' => $consultationRequest->client->user->name,
                    ],
                    'lawyer' => [
                        'id' => $consultationRequest->lawyer->lawyer_id,
                        'user_id' => $lawyerUserId,
                        'name' => $consultationRequest->lawyer->user->name,
                    ],
                    'consultation_request_id' => $consultationRequest->id,
                    'status' => $chat->status,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create chat session', $e->getMessage());
        }
    }

    /**
     * Get messages for a specific chat.
     *
     * @param int $chatId
     * @return JsonResponse
     */
    public function getMessages(int $chatId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $chat = Chat::findOrFail($chatId);

        // Check if the user is part of this chat
        if ($user->id !== $chat->client_id && $user->id !== $chat->lawyer_id) {
            return $this->forbiddenResponse('You are not authorized to view these messages');
        }

        $messages = ChatMessage::where('chat_id', $chatId)
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $message->sender_id,
                        'name' => $message->sender->name,
                    ],
                    'content' => $message->content,
                    'read_at' => $message->read_at?->format('Y-m-d H:i:s'),
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send a message in a chat.
     *
     * @param Request $request
     * @param int $chatId
     * @return JsonResponse
     */
    public function sendMessage(Request $request, int $chatId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $chat = Chat::findOrFail($chatId);

        // Check if the user is part of this chat
        if ($user->id !== $chat->client_id && $user->id !== $chat->lawyer_id) {
            return $this->forbiddenResponse('You are not authorized to send messages in this chat');
        }

        // Check if the chat is active
        if (!$chat->isActive()) {
            return $this->forbiddenResponse('This chat is closed and cannot receive new messages');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        try {
            $message = ChatMessage::create([
                'chat_id' => $chatId,
                'sender_id' => $user->id,
                'content' => $validated['content'],
            ]);

            // Update the last_message_at timestamp
            $chat->last_message_at = now();
            $chat->save();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'content' => $message->content,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to send message', $e->getMessage());
        }
    }

    /**
     * Close a chat.
     *
     * @param int $chatId
     * @return JsonResponse
     */
    public function closeChat(int $chatId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $chat = Chat::findOrFail($chatId);

        // Check if the user is part of this chat
        if ($user->id !== $chat->client_id && $user->id !== $chat->lawyer_id) {
            return $this->forbiddenResponse('You are not authorized to close this chat');
        }

        // Check if the chat is already closed
        if ($chat->isClosed()) {
            return response()->json([
                'success' => true,
                'message' => 'Chat is already closed',
            ]);
        }

        try {
            $chat->status = Chat::STATUS_CLOSED;
            $chat->save();

            // Create a system message indicating the chat was closed
            $this->createSystemMessage($chat, "Chat closed by " . ($user->id === $chat->client_id ? 'client' : 'lawyer'));

            return response()->json([
                'success' => true,
                'message' => 'Chat closed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to close chat', $e->getMessage());
        }
    }

    /**
     * Create a system message in a chat.
     *
     * @param Chat $chat
     * @param string $content
     * @return ChatMessage
     */
    private function createSystemMessage(Chat $chat, string $content): ChatMessage
    {
        // Use a system user ID or the client's ID for system messages
        $systemUserId = $chat->client_id;

        return ChatMessage::create([
            'chat_id' => $chat->id,
            'sender_id' => $systemUserId,
            'content' => $content,
            'read_at' => now(), // System messages are considered read immediately
        ]);
    }

    /**
     * Return unauthorized response.
     *
     * @return JsonResponse
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    /**
     * Return forbidden response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Return payment required response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function paymentRequiredResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 402); // 402 Payment Required
    }

    /**
     * Return server error response.
     *
     * @param string $message
     * @param string|null $error
     * @return JsonResponse
     */
    private function serverErrorResponse(string $message, ?string $error = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($error) {
            $response['error'] = $error;
        }

        return response()->json($response, 500);
    }
} 