<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    // GET /api/addresses
    public function index(Request $request)
    {
        $user = $request->user();

        $items = Address::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    // GET /api/addresses/{id}
public function show(Request $request, $id)
{
    $user = $request->user();

    $addr = Address::where('user_id', $user->id)->findOrFail($id);

    return response()->json([
        'address' => $addr,
    ]);
}


    // POST /api/addresses
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $this->validateData($request);

        $data['label'] = $this->normalizeLabel($data['label'] ?? 'home');
        $data['user_id'] = $user->id;

        // أول عنوان أو تم تحديده كافتراضي
        $shouldBeDefault = ($data['is_default'] ?? false)
            || !Address::where('user_id', $user->id)->exists();

        return DB::transaction(function () use ($data, $user, $shouldBeDefault) {
            if ($shouldBeDefault) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
                $data['is_default'] = false;
            }

            $addr = Address::create($data);

            return response()->json([
                'message' => 'تم حفظ العنوان.',
                'address' => $addr,
            ], 201);
        });
    }

    // PUT /api/addresses/{id}
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $addr = Address::where('user_id', $user->id)->findOrFail($id);

        $data = $this->validateData($request, true);
        if (isset($data['label'])) {
            $data['label'] = $this->normalizeLabel($data['label']);
        }

        return DB::transaction(function () use ($addr, $user, $data) {
            // ضبط الافتراضي
            if (!empty($data['is_default'])) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            $addr->update($data);

            return response()->json([
                'message' => 'تم تحديث العنوان.',
                'address' => $addr->fresh(),
            ]);
        });
    }

    // DELETE /api/addresses/{id}
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $addr = Address::where('user_id', $user->id)->findOrFail($id);

        return DB::transaction(function () use ($user, $addr) {
            $wasDefault = $addr->is_default;
            $addr->delete();

            // لو حذف الافتراضي، عيّن واحد تاني تلقائياً (الأحدث)
            if ($wasDefault) {
                $next = Address::where('user_id', $user->id)->latest('id')->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }

            return response()->json(['message' => 'تم حذف العنوان.']);
        });
    }

    // PUT /api/addresses/{id}/default
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $addr = Address::where('user_id', $user->id)->findOrFail($id);

        return DB::transaction(function () use ($user, $addr) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
            $addr->update(['is_default' => true]);

            return response()->json([
                'message' => 'تم تعيين العنوان الافتراضي.',
                'address' => $addr->fresh(),
            ]);
        });
    }

    // ================= helpers =================

    private function validateData(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'label'          => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:16'],
            'title'          => ['nullable', 'string', 'max:120'],
            'details'        => ['nullable', 'string', 'max:255'],
            'street'         => ['nullable', 'string', 'max:120'],
            'floor'          => ['nullable', 'string', 'max:60'],
            'city'           => ['nullable', 'string', 'max:120'],
            'contact_phone'  => ['nullable', 'string', 'max:20', 'regex:/^09\d{8}$/'], // سوري: 09XXXXXXXX
            'lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'lng'            => ['nullable', 'numeric', 'between:-180,180'],
            'is_default'     => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function normalizeLabel(?string $label): string
    {
        $l = trim(mb_strtolower($label ?? 'home'));
        // قبول عربي/إنكليزي:
        if (in_array($l, ['home','المنزل','البيت'])) return 'home';
        if (in_array($l, ['work','العمل','الشغل']))  return 'work';
        return 'other'; // 'غير ذلك'
    }
}
