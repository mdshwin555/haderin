<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    // GET /api/banners?limit=10
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $now   = now();

        $items = Banner::query()
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderByDesc('sort_order')
            ->orderByDesc('id')   // ترتيب ثابت حتى لو created_at = NULL
            ->take($limit)
            ->get()
            ->map(fn (Banner $b) => $this->mapBanner($b));

        return response()->json(['data' => $items], 200);
    }

    // POST /api/banners  (multipart/form-data)
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link_url'    => ['nullable', 'url', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image'       => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'image.required' => 'يرجى رفع صورة البانر.',
        ]);

        // بيرجع شكل: banners/filename.ext
        $path = $request->file('image')->store('banners', 'public');

        $b = Banner::create([
            'title'       => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'link_url'    => $data['link_url'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'starts_at'   => $data['starts_at'] ?? null,
            'ends_at'     => $data['ends_at'] ?? null,
            'image_path'  => $path,
        ]);

        return response()->json(['data' => $this->mapBanner($b)], 201);
    }

    // PUT /api/banners/{banner}
    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title'       => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link_url'    => ['nullable', 'url', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $banner->image_path = $request->file('image')->store('banners', 'public');
        }

        $banner->fill([
            'title'       => $data['title']       ?? $banner->title,
            'description' => $data['description'] ?? $banner->description,
            'link_url'    => $data['link_url']    ?? $banner->link_url,
            'is_active'   => isset($data['is_active']) ? (bool)$data['is_active'] : $banner->is_active,
            'sort_order'  => isset($data['sort_order']) ? (int)$data['sort_order'] : $banner->sort_order,
            'starts_at'   => array_key_exists('starts_at', $data) ? $data['starts_at'] : $banner->starts_at,
            'ends_at'     => array_key_exists('ends_at', $data)   ? $data['ends_at']   : $banner->ends_at,
        ])->save();

        return response()->json(['data' => $this->mapBanner($banner->fresh())], 200);
    }

    // DELETE /api/banners/{banner}
    public function destroy(Banner $banner)
    {
        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();

        return response()->json(['message' => 'تم حذف البانر.'], 200);
    }

    // POST /api/banners/{banner}/view
    public function addView(Request $request, Banner $banner)
    {
        $banner->increment('views_count');
        return response()->json(['views_count' => (int) $banner->views_count], 200);
    }

    // ===== Helper =====
    private function mapBanner(Banner $b): array
    {
        // نضمن إن القيمة تحتوي مجلد banners/ وإنها URL جاهز
        $path = trim($b->image_path ?? '', '/');

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $url = $path; // أصلاً URL كامل
        } else {
            if ($path !== '' && !preg_match('#^banners/#', $path)) {
                $path = "banners/$path";
            }
            $url = asset('storage/' . $path); // http://HOST:PORT/storage/banners/...
        }

        return [
            'id'          => $b->id,
            'title'       => $b->title,
            'description' => $b->description,
            'image_url'   => $url,
            'link_url'    => $b->link_url,
            'is_active'   => (bool) $b->is_active,
            'sort_order'  => (int) $b->sort_order,
            'views_count' => (int) $b->views_count,
            'starts_at'   => optional($b->starts_at)?->toIso8601String(),
            'ends_at'     => optional($b->ends_at)?->toIso8601String(),
            'created_at'  => optional($b->created_at)?->toIso8601String(),
        ];
    }
}
