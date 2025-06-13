# Firebase Cloud Messaging (FCM) Notification Testing

This document provides instructions on how to test the FCM notification system in the application.

## Prerequisites

1. Make sure you have set up a Firebase project and added the service account credentials to `config/firebase.json`.
2. Ensure the `kreait/firebase-php` package is installed:
   ```bash
   composer require kreait/firebase-php
   ```

## FCM Token Registration and Update

The system is set up to handle FCM tokens in the following ways:

1. **During Registration**: When a user registers, the frontend should send the FCM token in the request:
   ```json
   {
     "name": "User Name",
     "email": "user@example.com",
     "password": "password123",
     "role": "client",
     "fcm_token": "your_fcm_token_here"
   }
   ```

2. **During Login**: When a user logs in, the frontend should update the FCM token:
   ```json
   {
     "email": "user@example.com",
     "password": "password123",
     "fcm_token": "updated_fcm_token_here"
   }
   ```

3. **During OTP Verification**: When verifying OTP for login:
   ```json
   {
     "email": "user@example.com",
     "otp": "123456",
     "fcm_token": "updated_fcm_token_here"
   }
   ```

## Testing Notifications

The system sends notifications for the following events:

### 1. When a Client Publishes a Case
- **Endpoint**: `POST /api/published-cases`
- **Receivers**: All lawyers in the target city with matching specialization
- **Notification Data**: Published case ID, case type

### 2. When a Client Sends a Direct Case Request to a Lawyer
- **Endpoint**: `POST /api/case-requests`
- **Receiver**: The targeted lawyer
- **Notification Data**: Request ID, case ID, case number

### 3. When a Lawyer Accepts/Rejects a Case Request
- **Endpoint**: `POST /api/case-requests/{id}/action`
- **Receiver**: The client who sent the request
- **Notification Data**: Request ID, case ID (if accepted)

### 4. When a Lawyer Submits an Offer for a Published Case
- **Endpoint**: `POST /api/published-cases/{id}/offers`
- **Receiver**: The client who published the case
- **Notification Data**: Published case ID, offer ID

### 5. When a Client Accepts/Rejects an Offer
- **Endpoint**: `POST /api/offers/{id}/accept` or `POST /api/offers/{id}/reject`
- **Receiver**: The lawyer who submitted the offer
- **Notification Data**: Offer ID, case ID (if accepted)

## Testing with Postman

1. Create a test user with an FCM token
2. Perform the actions listed above
3. Check your Firebase console for delivered messages
4. Verify that the notifications are received on the frontend

## Troubleshooting

If notifications are not being sent:

1. Check the Laravel logs for FCM errors: `storage/logs/laravel.log`
2. Verify that the FCM token is correctly stored in the database
3. Ensure the Firebase configuration in `config/firebase.json` is correct
4. Test the notification service directly:

```php
$user = \App\Models\User::find(1);
$notificationService = app(\App\Services\NotificationService::class);
$notificationService->sendToUser($user, 'Test Title', 'Test Message', ['key' => 'value']);
``` 