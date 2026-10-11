<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->addresses()->latest('is_default')->get()->map($this->present(...)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $address = DB::transaction(function () use ($request, $data): Address {
            $address = $request->user()->addresses()->create($data);

            if ($data['is_default'] ?? false) {
                $this->makeDefault($request, $address);
            }

            return $address;
        });

        return response()->json(['data' => $this->present($address->refresh())], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $data = $this->validated($request);
        $address->update($data);

        if ($data['is_default'] ?? false) {
            $this->makeDefault($request, $address);
        }

        return response()->json(['data' => $this->present($address->refresh())]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return response()->json(['message' => 'حُذف العنوان.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:64'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+?966|0)?5\d{8}$/'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ], [
            'phone.regex' => 'رقم الجوال يجب أن يكون رقم جوال سعودي صحيح.',
        ]);
    }

    /** عنوان افتراضي واحد فقط لكل مستخدم. */
    private function makeDefault(Request $request, Address $address): void
    {
        $request->user()->addresses()
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);
    }

    /** @return array<string, mixed> */
    private function present(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipientName' => $address->recipient_name,
            'phone' => $address->phone,
            'country' => $address->country,
            'city' => $address->city,
            'district' => $address->district,
            'street' => $address->street,
            'postalCode' => $address->postal_code,
            'notes' => $address->notes,
            'isDefault' => $address->is_default,
        ];
    }
}
