<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrivacyPolicyController extends Controller
{
    /**
     * GET /api/legal/privacy-policy?lang=ar
     * يرجّع أحدث سياسة خصوصية فعّالة.
     */
    public function show(Request $request)
    {
        $lang = $request->query('lang', 'ar');

        $policy = DB::table('privacy_policies')
            ->where('lang', $lang)
            ->where('is_active', true)
            ->where('title', 'سياسة الخصوصية')
            ->orderByDesc(DB::raw('COALESCE(published_at, created_at)'))
            ->first();

        if (!$policy) {
            return response()->json(['message' => 'لا توجد سياسة خصوصية متاحة.'], 404);
        }

        return response()->json([
            'title'        => $policy->title,
            'version'      => $policy->version,
            'lang'         => $policy->lang,
            'content'      => $policy->content,
            'published_at' => $policy->published_at,
        ]);
    }

    /**
     * GET /api/legal/terms?lang=ar
     * يرجّع أحدث "الشروط والأحكام" الفعّالة.
     */
    public function terms(Request $request)
    {
        $lang = $request->query('lang', 'ar');

        $terms = DB::table('privacy_policies')
            ->where('lang', $lang)
            ->where('is_active', true)
            ->where('title', 'الشروط والأحكام')
            ->orderByDesc(DB::raw('COALESCE(published_at, created_at)'))
            ->first();

        if (!$terms) {
            return response()->json(['message' => 'لا توجد شروط وأحكام متاحة.'], 404);
        }

        return response()->json([
            'title'        => $terms->title,
            'version'      => $terms->version,
            'lang'         => $terms->lang,
            'content'      => $terms->content,
            'published_at' => $terms->published_at,
        ]);
    }
}
