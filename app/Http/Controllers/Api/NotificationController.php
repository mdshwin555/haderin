<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications?limit=30&only_unread=0
    public function index(Request $request)
    {
        $user  = $request->user();
        $limit = (int) $request->query('limit', 30);
        $onlyUnread = (int) $request->query('only_unread', 0) === 1;

        $q = UserNotification::where('user_id', $user->id)
            ->latest();

        if ($onlyUnread) {
            $q->whereNull('read_at');
        }

        $items = $q->take($limit)->get()->map(function (UserNotification $n) {
            return [
                'id'         => $n->id,
                'title'      => $n->title,
                'body'       => $n->body,
                'type'       => $n->type,
                'icon'       => $n->icon,
                'data'       => $n->data,
                'is_read'    => $n->is_read,
                'read_at'    => optional($n->read_at)->toIso8601String(),
                'created_at' => optional($n->created_at)->toIso8601String(),
            ];
        });

        return response()->json(['data' => $items], 200);
    }

    // GET /api/notifications/unread-count
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = UserNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $count], 200);
    }

    // PATCH /api/notifications/{id}/read
    public function markOneRead(Request $request, $id)
    {
        $user = $request->user();

        $n = UserNotification::where('id', $id)
            ->where('user_id', $user->id)->first();

        if (!$n) return response()->json(['message' => 'غير موجود.'], 404);

        if (is_null($n->read_at)) {
            $n->read_at = now();
            $n->save();
        }

        return response()->json(['message' => 'تم التحديث.'], 200);
    }

    // POST /api/notifications/mark-all-read
    public function markAllRead(Request $request)
    {
        $user = $request->user();
        UserNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'تم تعليم جميع الإشعارات كمقروءة.'], 200);
    }
}
