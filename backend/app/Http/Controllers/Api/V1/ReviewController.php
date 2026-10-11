<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Painting;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    /** المراجعات المعتمدة فقط تظهر للعامة. */
    public function index(string $slug): AnonymousResourceCollection
    {
        $painting = Painting::published()->where('slug', $slug)->firstOrFail();

        $reviews = $painting->reviews()
            ->approved()
            ->with(['user:id,name', 'images'])
            ->latest()
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            $request->user()->reviews()->with(['painting', 'images'])->latest()->paginate(10)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'painting_id' => ['required', 'integer', 'exists:paintings,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        /*
         | لا مراجعة بلا شراء — إن كان الإعداد مفعّلاً.
         | بدون هذا القيد تتحوّل المراجعات إلى قناة دعاية لأي حساب مجاني.
         */
        $requirePurchase = (bool) (Setting::where('key', 'reviews.require_purchase')->value('value') ?? true);

        $order = Order::where('user_id', $user->id)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereHas('items', fn ($q) => $q->where('painting_id', $data['painting_id']))
            ->latest()
            ->first();

        if ($requirePurchase && $order === null) {
            throw ValidationException::withMessages([
                'painting_id' => ['يمكنك مراجعة الأعمال التي اقتنيتها فقط.'],
            ]);
        }

        if (Review::where('user_id', $user->id)->where('painting_id', $data['painting_id'])->exists()) {
            throw ValidationException::withMessages([
                'painting_id' => ['سبق أن كتبت مراجعة لهذا العمل. يمكنك تعديلها.'],
            ]);
        }

        $review = DB::transaction(function () use ($data, $user, $order, $request): Review {
            $review = Review::create([
                'painting_id' => $data['painting_id'],
                'user_id' => $user->id,
                'order_id' => $order?->id,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => ReviewStatus::Pending,
            ]);

            foreach ($request->file('images') ?? [] as $file) {
                $review->images()->create(['path' => $this->images->store($file, 'reviews')['path']]);
            }

            return $review;
        });

        return response()->json([
            'data' => new ReviewResource($review->load('images')),
            'message' => 'شكراً لك. ستُنشر مراجعتك بعد المراجعة.',
        ], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        // التعديل يُعيدها إلى الانتظار: نصّ اعتُمد ثم تغيّر يجب أن يُراجَع ثانيةً.
        $review->update([...$data, 'status' => ReviewStatus::Pending]);
        $this->recalculate($review->painting_id);

        return response()->json(['data' => new ReviewResource($review->fresh('images'))]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);

        $paintingId = $review->painting_id;
        $review->delete();
        $this->recalculate($paintingId);

        return response()->json(['message' => 'حُذفت المراجعة.']);
    }

    /** يعيد حساب متوسط التقييم من المراجعات المعتمدة وحدها. */
    public static function recalculate(int $paintingId): void
    {
        $stats = Review::where('painting_id', $paintingId)
            ->where('status', ReviewStatus::Approved->value)
            ->selectRaw('COUNT(*) AS c, COALESCE(AVG(rating), 0) AS a')
            ->first();

        Painting::whereKey($paintingId)->update([
            'rating_count' => (int) $stats->c,
            'rating_avg' => round((float) $stats->a, 2),
        ]);
    }
}
