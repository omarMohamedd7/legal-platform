<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\Client;
use App\Models\Lawyer;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    protected $notificationService;
    
    /**
     * Constructor
     * 
     * @param NotificationService $notificationService
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Get all contacts for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContacts()
    {
        $user = Auth::user();
        
        // Check if user is a client or lawyer
        $isClient = Client::where('user_id', $user->id)->exists();
        $isLawyer = Lawyer::where('user_id', $user->id)->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        // Initialize contacts first to ensure all contacts have real IDs
        $this->initializeContacts();
        
        // Get all contacts where the user is either the owner or the contact user
        $ownedContacts = Contact::where('user_id', $user->id)
            ->with('contactUser')
            ->get();
            
        $contactingContacts = Contact::where('contact_user_id', $user->id)
            ->with('user')
            ->get()
            ->map(function ($contact) {
                // Transform the contact to have the same structure as owned contacts
                // by swapping user_id and contact_user_id
                return (object)[
                    'id' => $contact->id,
                    'user_id' => $contact->contact_user_id,
                    'contact_user_id' => $contact->user_id,
                    'contactUser' => $contact->user,
                    'last_message_date' => $contact->last_message_date,
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at
                ];
            });
            
        // Combine both lists
        $contacts = $ownedContacts->concat($contactingContacts);
        
        // Remove duplicates by keeping only one contact per unique contact_user_id
        $uniqueContacts = collect();
        $seenContactUserIds = [];
        
        foreach ($contacts as $contact) {
            if (!in_array($contact->contact_user_id, $seenContactUserIds)) {
                $uniqueContacts->push($contact);
                $seenContactUserIds[] = $contact->contact_user_id;
            }
        }
        
        // Format contacts
        $formattedContacts = $uniqueContacts->map(function ($contact) {
            // Determine role based on the contactUser
            $role = 'unknown';
            if ($contact->contactUser->lawyer()->exists()) {
                $role = 'lawyer';
            } elseif ($contact->contactUser->client()->exists()) {
                $role = 'client';
            } elseif ($contact->contactUser->judge()->exists()) {
                $role = 'judge';
            }
            
            return [
                'id' => $contact->id,
                'name' => $contact->contactUser->name,
                'role' => $role,
                'userId' => $contact->contact_user_id,
                'lastMessageDate' => $contact->last_message_date,
                'lastMessage' => $this->getLastMessageForContact($contact->user_id, $contact->contact_user_id)
            ];
        })->toArray();
            
        return response()->json([
            'status' => 'success',
            'data' => $formattedContacts
        ]);
    }
    
    /**
     * Get the last message between two users
     *
     * @param int $userId
     * @param int $contactUserId
     * @return string|null
     */
    private function getLastMessageForContact($userId, $contactUserId)
    {
        $lastMessage = Message::where(function ($query) use ($userId, $contactUserId) {
                $query->where('sender_id', $userId)
                      ->where('receiver_id', $contactUserId);
            })
            ->orWhere(function ($query) use ($userId, $contactUserId) {
                $query->where('sender_id', $contactUserId)
                      ->where('receiver_id', $userId);
            })
            ->latest()
            ->first();
            
        return $lastMessage ? $lastMessage->message : null;
    }
    
    /**
     * Initialize contacts between clients and lawyers
     * This method creates contact entries for all clients and lawyers
     * to ensure they appear in each other's contact lists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function initializeContacts()
    {
        $user = Auth::user();
        
        // Check if user is a client or lawyer
        $isClient = Client::where('user_id', $user->id)->exists();
        $isLawyer = Lawyer::where('user_id', $user->id)->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        $contactsCreated = 0;
        
        // Get all lawyers and clients
        $lawyers = User::whereHas('lawyer')->where('id', '!=', $user->id)->get();
        $clients = User::whereHas('client')->where('id', '!=', $user->id)->get();
        
        if ($isClient) {
            // If user is a client, create contacts with all lawyers
            foreach ($lawyers as $lawyer) {
                $contact = Contact::firstOrCreate([
                    'user_id' => $user->id,
                    'contact_user_id' => $lawyer->id
                ], [
                    'last_message_date' => now()
                ]);
                
                if ($contact->wasRecentlyCreated) {
                    $contactsCreated++;
                }
                
                // Create reverse contact for the lawyer
                Contact::firstOrCreate([
                    'user_id' => $lawyer->id,
                    'contact_user_id' => $user->id
                ], [
                    'last_message_date' => now()
                ]);
            }
        } 
        
        if ($isLawyer) {
            // If user is a lawyer, create contacts with all clients
            foreach ($clients as $client) {
                $contact = Contact::firstOrCreate([
                    'user_id' => $user->id,
                    'contact_user_id' => $client->id
                ], [
                    'last_message_date' => now()
                ]);
                
                if ($contact->wasRecentlyCreated) {
                    $contactsCreated++;
                }
                
                // Create reverse contact for the client
                Contact::firstOrCreate([
                    'user_id' => $client->id,
                    'contact_user_id' => $user->id
                ], [
                    'last_message_date' => now()
                ]);
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => "Successfully initialized contacts. Created $contactsCreated new contacts.",
            'contacts_created' => $contactsCreated
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
        $isClient = Client::where('user_id', $user->id)->exists();
        $isLawyer = Lawyer::where('user_id', $user->id)->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        $contact = Contact::findOrFail($contactId);
        
        // Ensure the user is authorized to access this contact
        // User can access the contact if they are either the owner or the contact user
        if ($contact->user_id !== $user->id && $contact->contact_user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this contact'
            ], 403);
        }
        
        // Get the other user ID (either contact_user_id or user_id depending on who is accessing)
        $otherUserId = ($contact->user_id === $user->id) ? $contact->contact_user_id : $contact->user_id;
        
        // Get all messages between the user and the other user
        $messages = Message::where(function ($query) use ($user, $otherUserId) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', $otherUserId);
            })
            ->orWhere(function ($query) use ($user, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                      ->where('receiver_id', $user->id);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) use ($user) {
                // Format the response to match the frontend model
                return [
                    'id' => $message->id,
                    'senderId' => $message->sender_id,
                    'receiverId' => $message->receiver_id,
                    'message' => $message->message,
                    'isSender' => $message->sender_id === $user->id,
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
        $isClient = Client::where('user_id', $user->id)->exists();
        $isLawyer = Lawyer::where('user_id', $user->id)->exists();
        
        if (!$isClient && !$isLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only clients and lawyers can use the chat feature'
            ], 403);
        }
        
        // Get receiver user
        $receiver = User::findOrFail($receiverId);
        $isReceiverClient = Client::where('user_id', $receiver->id)->exists();
        $isReceiverLawyer = Lawyer::where('user_id', $receiver->id)->exists();
        
        // Check if the receiver is a valid user (client or lawyer)
        if (!$isReceiverClient && !$isReceiverLawyer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Receiver must be a client or lawyer'
            ], 403);
        }
        
        // Find existing contact or create a new one
        // First check if the contact exists where user is the owner
        $contact = Contact::where('user_id', $user->id)
            ->where('contact_user_id', $receiverId)
            ->first();
            
        // If not found, check if the contact exists where user is the contact user
        if (!$contact) {
            $contact = Contact::where('user_id', $receiverId)
                ->where('contact_user_id', $user->id)
                ->first();
        }
        
        // If still not found, create a new contact
        if (!$contact) {
            $contact = Contact::create([
                'user_id' => $user->id,
                'contact_user_id' => $receiverId,
                'last_message_date' => now()
            ]);
            
            // Create a contact for the receiver as well
            Contact::create([
                'user_id' => $receiverId,
                'contact_user_id' => $user->id,
                'last_message_date' => now()
            ]);
        } else {
            // Update the last message date
            $contact->update(['last_message_date' => now()]);
            
            // Update the last message date for the other contact as well
            Contact::where('user_id', $receiverId)
                ->where('contact_user_id', $user->id)
                ->update(['last_message_date' => now()]);
        }
        
        // Create the message
        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $messageText
        ]);
        
        // Create notification data with sender_id and message
        $notificationData = [
            'sender_id' => $user->id,
            'message' => $messageText
        ];
        
        // Send push notification to the receiver
        if ($receiver->fcm_token) {
            $title = $user->name;
            $body = $messageText;
            
            // Send push notification with the required data
            $this->notificationService->sendToUser(
                $receiver, 
                $title, 
                $body, 
                $notificationData
            );
        }
        
        // Create in-app notification
        $this->notificationService->sendNotification(
            $receiverId,
            'new_message',
            "New message from {$user->name}",
            $notificationData
        );
        
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
