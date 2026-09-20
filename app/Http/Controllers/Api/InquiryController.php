<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inquiry\InquiryService;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/inquiries — "Make it for me" from a finished calculation. */
class InquiryController extends Controller
{
    /** POST /api/spare-parts (multipart): photos + what it is, measurements, use and load → a modelling + printing inquiry */
    public function spare(Request $request, InquiryService $service): JsonResponse
    {
        $data = $request->validate([
            'what' => ['required', 'string', 'min:5', 'max:1500'],
            'use' => ['nullable', 'string', 'max:1000'],
            'load' => ['nullable', 'in:none,light,medium,heavy,unknown'],
            'environment' => ['nullable', 'array', 'max:5'],
            'environment.*' => ['in:outdoor,heat,water,food,flexible'],
            'dim_x' => ['nullable', 'numeric', 'min:0.5', 'max:2000'],
            'dim_y' => ['nullable', 'numeric', 'min:0.5', 'max:2000'],
            'dim_z' => ['nullable', 'numeric', 'min:0.5', 'max:2000'],
            'material' => ['nullable', 'string', 'max:10'],
            'original_available' => ['nullable', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'zip' => ['required', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'note' => ['nullable', 'string', 'max:2000'],
            'wanted_by' => ['nullable', 'date', 'after:today'],
            'delivery_pref' => ['nullable', 'in:any,pickup,shipping'],
            'website' => ['prohibited'], // honeypot
        ]);
        $inquiry = $service->createSparePart($data, $request->file('photos', []), $request->user());

        return response()->json(['inquiry' => ['token' => $inquiry->token, 'url' => route('inquiry.show', $inquiry), 'needs_verification' => $inquiry->verified_at === null]], 201);
    }

    public function store(Request $request, InquiryService $service): JsonResponse
    {
        $data = $request->validate([
            'calculation' => ['required', 'string', 'max:16'],
            'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'zip' => ['required', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:40'],
            'wanted_by' => ['nullable', 'date', 'after:today'],
            'delivery_pref' => ['nullable', 'in:any,pickup,shipping'],
            'website' => ['prohibited'], // honeypot
        ]);
        $calc = Calculation::with('modelFile')->where('token', $data['calculation'])->firstOrFail();
        $session = $request->attributes->get('anon_session');
        if ($calc->owner_user_id && $request->user()?->id !== $calc->owner_user_id) {
            abort(403);
        }

        $inquiry = $service->create($calc, $data, $request->user(), $session);

        return response()->json([
            'inquiry' => ['token' => $inquiry->token, 'status' => $inquiry->status, 'url' => route('inquiry.show', $inquiry), 'needs_verification' => $inquiry->verified_at === null],
        ], 201);
    }
}
