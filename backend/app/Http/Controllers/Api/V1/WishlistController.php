<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaintingResource;
use App\Models\Painting;
use App\Models\WishlistItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WishlistController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paintings = Painting::published()
            ->whereIn('id', $request->user()->wishlistItems()->pluck('painting_id'))
            ->with(['category', 'mainImage'])
            ->get();

        return PaintingResource::collection($paintings);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'painting_id' => ['required', 'integer', 'exists:paintings,id'],
        ]);

        // firstOrCreate لا create: الضغط مرّتين على القلب يجب ألّا يُنتج خطأ.
        WishlistItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'painting_id' => $data['painting_id'],
        ]);

        return response()->json(['message' => 'أُضيفت إلى المفضّلة.'], 201);
    }

    public function destroy(Request $request, int $paintingId): JsonResponse
    {
        $request->user()->wishlistItems()->where('painting_id', $paintingId)->delete();

        return response()->json(['message' => 'أُزيلت من المفضّلة.']);
    }

    /** ينقل عملاً من المفضّلة إلى السلة في خطوة واحدة. */
    public function moveToCart(Request $request, int $paintingId): JsonResponse
    {
        $painting = Painting::published()->findOrFail($paintingId);
        $cart = $this->carts->forUser($request->user());

        $this->carts->add($cart, $painting);
        $request->user()->wishlistItems()->where('painting_id', $paintingId)->delete();

        return response()->json(['message' => 'نُقلت إلى السلة.']);
    }
}
