git# Chat Feature Testing Guide

This guide explains how to test the chat feature including push notifications.

## Overview

The chat feature allows communication between clients and lawyers through:
1. Text messages
2. Push notifications
3. In-app notifications

## Testing Prerequisites

1. A running Laravel server (`php artisan serve`)
2. Access to the Postman collection (`chat_api_postman_collection.json`)
3. Two devices or browser tabs to simulate a client and a lawyer

## Testing Steps

### 1. Set Up FCM Tokens

When logging in, provide an FCM token to receive push notifications:

```json
{
  "email": "ahmed@client.com",
  "password": "password",
  "fcm_token": "test_fcm_token_for_client"
}
```

### 2. Initialize Contacts (Important First Step)

Before starting to chat, you need to initialize contacts to see other users in your contact list:

1. **Login as Client or Lawyer**
2. **Initialize Contacts**:
   - Send POST request to `/contacts/initialize`
   - This will create contact entries between you and all available users of the opposite role
   - Response will show how many contacts were created:
     ```json
     {
       "status": "success",
       "message": "Successfully initialized contacts. Created 3 new contacts.",
       "contacts_created": 3
     }
     ```
3. **Get Contacts**:
   - Now when you send GET request to `/contacts`, you should see all available users

### 3. Client-Lawyer Chat Flow

#### As Client:

1. **Login as Client**:
   - Use `ahmed@client.com` / `password`
   - Save the token in Postman environment

2. **Get Contacts**:
   - Send GET request to `/contacts`
   - Note the lawyer contact IDs

3. **Send Message to Lawyer**:
   - Send POST request to `/messages`
   - Include:
     ```json
     {
       "receiver_id": "4", // Lawyer ID
       "message": "Hello, I need legal advice"
     }
     ```

4. **Check Notifications** (optional):
   - Send GET request to `/notifications`
   - Verify your messages are recorded

#### As Lawyer:

1. **Login as Lawyer**:
   - Use `fahad@lawyer.com` / `password`
   - Save the token in Postman environment

2. **Get Contacts**:
   - Send GET request to `/contacts`
   - Verify the client appears in the list

3. **Get Chat History**:
   - Send GET request to `/chat/{contactId}`
   - Verify the client's message appears

4. **Check Notifications**:
   - Send GET request to `/notifications`
   - Verify the client's message created a notification
   - The notification data contains:
     ```json
     {
       "sender_id": 1,
       "message": "Hello, I need legal advice"
     }
     ```

5. **Reply to Client**:
   - Send POST request to `/messages`
   - Include:
     ```json
     {
       "receiver_id": "1", // Client ID
       "message": "I can help with your legal issue"
     }
     ```

### 4. Push Notification Structure

When a message is sent, a push notification is sent to the receiver with:

- **Title**: Sender's name
- **Body**: Message content
- **Data**:
  ```json
  {
    "sender_id": 1, // ID of the sender
    "message": "Hello, I need legal advice" // Message content
  }
  ```

This data structure allows the Flutter app to:
1. Display the notification
2. Navigate to the correct chat screen when tapped
3. Update the UI in real-time

## Troubleshooting

- **Empty Contacts List**: If your contacts list is empty, use the `/contacts/initialize` endpoint to create contacts
- **No FCM Token**: If no FCM token is provided, in-app notifications will still work but push notifications won't be sent
- **Authorization Errors**: Make sure you're using the correct token in the Authorization header
- **403 Forbidden**: Only clients and lawyers can communicate with each other

## API Endpoints

### Chat
- `GET /contacts` - Get all contacts
- `POST /contacts/initialize` - Initialize contacts between clients and lawyers
- `GET /chat/{contactId}` - Get chat history with a contact
- `POST /messages` - Send a message

### Notifications
- `GET /notifications` - Get all notifications
- `POST /notifications/{id}/read` - Mark notification as read
- `POST /notifications/read-all` - Mark all notifications as read 