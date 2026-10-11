<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Setting::orderBy('group')->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->pluck('value', 'key')),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['present'],
        ]);

        foreach ($data['settings'] as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']],
            );
        }

        // الصفحة الرئيسية مُخزَّنة مؤقتاً؛ تغيير الإعدادات يجب أن يظهر فوراً.
        Cache::forget('home:sections');

        return response()->json(['message' => 'حُفظت الإعدادات.']);
    }
}
