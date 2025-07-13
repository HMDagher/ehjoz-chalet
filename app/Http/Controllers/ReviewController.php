<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

class ReviewController extends Controller
{
    public function showForm($token)
    {
        $review = Review::where('review_token', $token)
            ->whereNull('review_token_used_at')
            ->where('review_token_expires_at', '>', now())
            ->first();

        if (!$review) {
            return response()->view('Frontend.Pages.review-link-invalid', [], 404);
        }

        return view('Frontend.Pages.review-form', [
            'review' => $review,
            'token' => $token,
        ]);
    }

    public function submitForm(Request $request, $token)
    {
        $review = Review::where('review_token', $token)
            ->whereNull('review_token_used_at')
            ->where('review_token_expires_at', '>', now())
            ->first();

        if (!$review) {
            return response()->view('Frontend.Pages.review-link-invalid', [], 404);
        }

        $data = $request->validate([
            'overall_rating' => 'required|integer|min:1|max:5',
            'cleanliness_rating' => 'nullable|integer|min:1|max:5',
            'location_rating' => 'nullable|integer|min:1|max:5',
            'value_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->fill($data);
        $review->review_token_used_at = now();
        $review->save();

        return view('Frontend.Pages.review-thank-you');
    }
} 