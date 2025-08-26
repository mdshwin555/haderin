<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SupportChatController extends Controller
{
    // يبدأ محادثة جديدة للعميل أو يرجع المفتوحة
    public function start(Request $request)
    {
        $user = $request->user();

        // فقط العملاء يبدأون محادثة (الدعم يقرأ من index)
        if ($user->role !== 'customer') {
            return response()->json(['message' => 'مسموح للعملاء فقط.'], 403);
        }

        $conv = Conversation::where('customer_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$conv) {
            $conv = Conversation::create([
                'customer_id'     => $user->id,
                'status'          => 'open',
                'last_message_at' => Carbon::now(),
            ]);

            // رسالة ترحيب سيستم اختيارية
            Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $user->id,
                'sender_role'     => 'system',
                'body'            => 'أهلاً بك! اكتب سؤالك وسيقوم فريق الدعم بالرد.',
                'seen_by_customer'=> true,
            ]);
        }

        $messages = $conv->messages()
            ->orderBy('id', 'asc')
            ->take(50)
            ->get()
            ->map(fn ($m) => $this->presentMessage($m));

        return response()->json([
            'conversation_id' => $conv->id,
            'status'          => $conv->status,
            'poll_every_sec'  => 10,
            'messages'        => $messages,
        ]);
    }

    // جلب الرسائل (يدعم after_id للـ polling)
    public function messages(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$this->canAccess($user->id, $user->role, $conversation)) {
            return response()->json(['message' => 'غير مسموح.'], 403);
        }

        $afterId = (int) $request->query('after_id', 0);
        $limit   = min((int) $request->query('limit', 50), 200);

        $q = $conversation->messages()->orderBy('id', 'asc');
        if ($afterId > 0) {
            $q->where('id', '>', $afterId);
        }

        $msgs = $q->take($limit)->get()->map(fn ($m) => $this->presentMessage($m));

        // تحديث مؤشرات قراءة بسيطة
        if ($msgs->count() > 0) {
            if ($user->role === 'customer') {
                Message::where('conversation_id', $conversation->id)
                    ->where('sender_role', 'support')
                    ->update(['seen_by_customer' => true]);
            } elseif ($user->role === 'support') {
                Message::where('conversation_id', $conversation->id)
                    ->where('sender_role', 'customer')
                    ->update(['seen_by_support' => true]);
            }
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages'        => $msgs,
        ]);
    }

    // إرسال رسالة من العميل أو موظف الدعم
    public function send(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        if (!$this->canAccess($user->id, $user->role, $conversation)) {
            return response()->json(['message' => 'غير مسموح.'], 403);
        }

        $data = $request->validate([
            'body' => ['required','string','max:5000'],
        ]);

        $role = ($user->role === 'support' || $user->role === 'admin') ? 'support' : 'customer';

        // ربط الوكيل بالمحادثة إذا أول رسالة من الدعم
        if ($role === 'support' && !$conversation->agent_id) {
            $conversation->agent_id = $user->id;
        }

        $conversation->last_message_at = Carbon::now();
        $conversation->save();

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'sender_role'     => $role,
            'body'            => $data['body'],
            'seen_by_customer'=> ($role === 'customer'),
            'seen_by_support' => ($role === 'support'),
        ]);

        return response()->json([
            'message' => 'تم الإرسال.',
            'data'    => $this->presentMessage($msg),
        ], 201);
    }

    // قائمة المحادثات لموظفي الدعم
    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['support','admin'])) {
            return response()->json(['message' => 'خاص بالدعم.'], 403);
        }

        $convs = Conversation::with('customer:id,full_name,phone')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json($convs);
    }

    // =========== Helpers ===========
    private function canAccess(int $userId, string $role, Conversation $c): bool
    {
        if (in_array($role, ['support','admin'])) return true;
        return $c->customer_id === $userId;
    }

    private function presentMessage(Message $m): array
    {
        return [
            'id'           => $m->id,
            'from'         => $m->sender_role,         // 'customer' | 'support' | 'system'
            'sender_id'    => $m->sender_id,
            'body'         => $m->body,
            'seen_customer'=> (bool) $m->seen_by_customer,
            'seen_support' => (bool) $m->seen_by_support,
            'created_at'   => $m->created_at?->toIso8601String(),
        ];
    }
}
