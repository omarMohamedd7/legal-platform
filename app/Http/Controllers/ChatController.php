<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\Client;
use App\Models\Lawyer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get all contacts for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContacts()
    {
        $user = Auth::user();
        
        // Check if user is a client or lawyer
        $isClient = $user->client()->exists();
        $isLawyer = $user->lawyer()->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        // Get all contacts where the user is either the user or the contact
        $contacts = Contact::where('user_id', $user->id)
            ->with('contactUser')
            ->get()
            ->map(function ($contact) {
                // Format the response to match the frontend model
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'role' => $contact->role,
                    'lastMessageDate' => $contact->last_message_date,
                    'lastMessage' => $contact->last_message ? $contact->last_message->message : null
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $contacts
        ]);
    }
    
    /**
     * Get chat history with a specific contact
     *
     * @param int $contactId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChat($contactId)
    {
        $user = Auth::user();
        
        // Check if user is a client or lawyer
        $isClient = $user->client()->exists();
        $isLawyer = $user->lawyer()->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        $contact = Contact::findOrFail($contactId);
        
        // Ensure the user is authorized to access this contact
        if ($contact->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this contact'
            ], 403);
        }
        
        // Get all messages between the user and the contact
        $messages = Message::where(function ($query) use ($user, $contact) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', $contact->contact_user_id);
            })
            ->orWhere(function ($query) use ($user, $contact) {
                $query->where('sender_id', $contact->contact_user_id)
                      ->where('receiver_id', $user->id);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                // Format the response to match the frontend model
                return [
                    'id' => $message->id,
                    'senderId' => $message->sender_id,
                    'receiverId' => $message->receiver_id,
                    'message' => $message->message,
                    'isSender' => $message->is_sender,
                    'createdAt' => $message->created_at
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }
    
    /**
     * Send a message to a user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $receiverId = $request->receiver_id;
        $messageText = $request->message;
        
        // Check if user is a client or lawyer
        $isClient = $user->client()->exists();
        $isLawyer = $user->lawyer()->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        // Get receiver user
        $receiver = User::findOrFail($receiverId);
        $isReceiverClient = $receiver->client()->exists();
        $isReceiverLawyer = $receiver->lawyer()->exists();
        
        // Check if the communication is between a client and a lawyer
        if (($isClient && !$isReceiverLawyer) || ($isLawyer && !$isReceiverClient)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Communication is only allowed between clients and lawyers'
            ], 403);
        }
        
        // Create or update contact
        $contact = Contact::firstOrCreate(
            [
                'user_id' => $user->id,
                'contact_user_id' => $receiverId
            ],
            [
                'last_message_date' => now()
            ]
        );
        
        // Update the last message date
        $contact->update(['last_message_date' => now()]);
        
        // Create a contact for the receiver as well if it doesn't exist
        Contact::firstOrCreate(
            [
                'user_id' => $receiverId,
                'contact_user_id' => $user->id
            ],
            [
                'last_message_date' => now()
            ]
        );
        
        // Create the message
        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $messageText
        ]);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $message->id,
                'senderId' => $message->sender_id,
                'receiverId' => $message->receiver_id,
                'message' => $message->message,
                'isSender' => true,
                'createdAt' => $message->created_at
            ]
        ], 201);
    }
}
