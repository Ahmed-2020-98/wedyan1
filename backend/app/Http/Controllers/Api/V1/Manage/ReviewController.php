<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\V1\ReviewController as PublicReviewController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Review::with(['user:id,name', 'painting:id,title,slug', 'images']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ReviewResource::collection($query->latest()->paginate(20));
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'status' => ReviewStatus::from($data['status']),
            'moderation_note' => $data['moderation_note'] ?? null,
        ]);

        // متوسط التقييم يتغيّر باعتماد المراجعة أو رفضها، فيُعاد حسابه هنا.
        PublicReviewController::recalculate($review->painting_id);

        return response()->json([
            'data' => new ReviewResource($review->fresh(['user', 'painting', 'images'])),
        ]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $paintingId = $review->painting_id;
        $review->delete();
        PublicReviewController::recalculate($paintingId);

        return response()->json(['message' => 'حُذفت المراجعة.']);
    }
}
