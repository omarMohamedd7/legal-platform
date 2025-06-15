<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $onlyUnread = $request->boolean('unread', false);

        $notifications = $this->notificationService->getNotifications(
            $request->user()->id,
            $limit,
            $offset,
            $onlyUnread
        );

        return response()->json($notifications);
    }

    /**
     * Mark a notification as read
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, string $id)
    {
        $success = $this->notificationService->markAsRead(
            $request->user()->id,
            $id
        );

        if (!$success) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'message' => "{$count} notifications marked as read"
        ]);
    }

    /**
     * Delete a notification
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, string $id)
    {
        $success = $this->notificationService->deleteNotification(
            $request->user()->id,
            $id
        );

        if (!$success) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification deleted']);
    }
} 