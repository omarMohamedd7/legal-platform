# API Resources

This directory contains Laravel API Resource classes that standardize the JSON responses in the application.

## Response Structure

All API responses follow this standard format:

```json
{
  "success": true,
  "message": "Optional message about the operation",
  "data": {
    // Resource-specific data here
  }
}
```

## Available Resources

- `BaseResource`: Abstract base class for all resources
- `BaseResourceCollection`: Base class for paginated responses
- `BaseJsonResourceWrapping`: Trait that adds response wrapping functionality
- `UserResource`: User data with conditional profile information based on role
- `ClientResource`: Client-specific data
- `LawyerResource`: Lawyer-specific data
- `JudgeResource`: Judge-specific data
- `ConsultationRequestResource`: Consultation request data with related resources
- `PaymentResource`: Payment data

## Usage Example

In your controller:

```php
// Return a single resource
return (new UserResource($user))
    ->withMessage('User created successfully');

// Return a collection with pagination
return new UserCollection(User::paginate(10));
```

## Resource Relationships

Resources can include related resources when they are loaded. For example, the `UserResource` will include role-specific information when the appropriate relationship is loaded:

```php
// In your controller
$user = User::with('client')->findOrFail($id);
return new UserResource($user);
```

This will include the client profile information in the response if the user is a client. 