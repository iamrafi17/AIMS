<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemNotification::where('user_id', $request->user()->id)->latest();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return response()->json([
            'unread_count' => SystemNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'notifications' => $query->paginate(20),
        ]);
    }

    public function read(Request $request, SystemNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request)
    {
        SystemNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
