<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ChaletSearchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class pageController extends baseController
{
    // homepage one
    public function index()
    {
        $featuredChalets = \App\Models\Chalet::where('is_featured', true)
            ->where('status', \App\Enums\ChaletStatus::Active)
            ->where(function ($query) {
                $query->whereNull('featured_until')
                    ->orWhere('featured_until', '>=', now());
            })
            ->with('timeSlots')
            ->get();

        // Debug: Log featured chalets and their slots
        \Log::info('Featured chalets raw', [
            'count' => $featuredChalets->count(),
            'ids' => $featuredChalets->pluck('id')->toArray(),
            'slots_count' => $featuredChalets->mapWithKeys(fn ($c) => [$c->id => $c->timeSlots->count()])->toArray(),
        ]);

        // Just send the featured chalets (with their slots) to the view, no availability check
        $featuredChaletsWithSlots = [];
        foreach ($featuredChalets as $chalet) {
            $slots = $chalet->timeSlots
                ->where('is_active', true)
                ->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $slot->price ?? null,
                        'is_overnight' => $slot->is_overnight,
                    ];
                })->values()->toArray();

            $featuredChaletsWithSlots[] = [
                'chalet' => $chalet,
                'slots' => $slots,
            ];
        }

        \Log::info('Featured chalets sent to index', [
            'count' => count($featuredChaletsWithSlots),
            'ids' => array_map(fn ($c) => $c['chalet']->id, $featuredChaletsWithSlots),
        ]);

        return $this->view('index', [
            'featuredChaletsWithSlots' => $featuredChaletsWithSlots,
        ]);
    }

    // room three page
    public function chalets(Request $request)
    {
        $results = [];
        $is_search = false;

        // Get search parameters
        $checkin = $request->input('check__in');
        $checkout = $request->input('check__out');
        $bookingType = $request->input('booking_type', 'overnight');

        // Convert date format from d-m-Y to Y-m-d if present
        if ($checkin) {
            try {
                $checkin = \Carbon\Carbon::createFromFormat('d-m-Y', $checkin)->format('Y-m-d');
            } catch (\Exception $e) {
                \Log::error('Invalid checkin date format', ['input' => $checkin]);
            }
        }
        if ($checkout) {
            try {
                $checkout = \Carbon\Carbon::createFromFormat('d-m-Y', $checkout)->format('Y-m-d');
            } catch (\Exception $e) {
                \Log::error('Invalid checkout date format', ['input' => $checkout]);
            }
        }

        // Resolve the new search service from the container (it has dependencies)
        $chaletSearchService = app(ChaletSearchService::class);

        if ($request->filled('check__in')) {
            $is_search = true;
            try {
                // Debug information
                \Log::info('Search parameters', [
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'bookingType' => $bookingType,
                ]);

                // Build search params for the new service
                $searchParams = [
                    'booking_type' => $bookingType === 'day-use' ? 'day-use' : 'overnight',
                    'start_date' => $checkin,
                    'end_date' => $bookingType === 'overnight'
                        ? ($checkout ?: ($checkin ? Carbon::parse($checkin)->copy()->addDay()->format('Y-m-d') : null))
                        : null,
                ];

                // Call the new search
                $searchResponse = $chaletSearchService->searchAvailableChalets($searchParams);

                if (! ($searchResponse['success'] ?? false)) {
                    $errors = implode(', ', $searchResponse['errors'] ?? []);
                    \Log::warning('Search validation failed', ['errors' => $errors]);
                    return redirect()->back()->with('error', $errors ?: 'Invalid search parameters');
                }

                // Map new service results to the view's expected structure
                $results = [];
                $chaletIds = collect($searchResponse['chalets'])->pluck('chalet_id')->unique()->values();
                $chalets = \App\Models\Chalet::with('media')->whereIn('id', $chaletIds)->get()->keyBy('id');

                foreach ($searchResponse['chalets'] as $item) {
                    $chaletModel = $chalets[$item['chalet_id']] ?? null;
                    if (! $chaletModel) {
                        continue;
                    }

                    // Extract pricing summary
                    $minPrice = $item['pricing']['min_price'] ?? null;

                    // Extract available slots and their pricing
                    $slots = [];
                    if (! empty($item['availability']['available_slots'])) {
                        $pricingBreakdown = collect($item['pricing']['price_breakdown'] ?? [])->groupBy('slot_id');

                        foreach ($item['availability']['available_slots'] as $slot) {
                            $slotId = $slot['slot_id'];
                            $slotPricing = $pricingBreakdown->get($slotId);

                            if ($slotPricing) {
                                $isOvernight = $slot['is_overnight'] ?? false;
                                $slotData = [
                                    'id' => $slotId,
                                    'name' => $slot['slot_name'] ?? ($isOvernight ? 'Overnight Stay' : "{$slot['start_time']} - {$slot['end_time']}"),
                                    'start_time' => $slot['start_time'],
                                    'end_time' => $slot['end_time'],
                                ];

                                if ($isOvernight) {
                                    // For overnight, we show price per night and total
                                    $slotData['price_per_night'] = $item['pricing']['min_price']; // Use the calculated min_price for consistency
                                    $slotData['total_price'] = $slotPricing->sum('final_price');
                                    $slotData['nights'] = isset($searchParams['end_date']) && $searchParams['end_date'] ? Carbon::parse($searchParams['start_date'])->diffInDays(Carbon::parse($searchParams['end_date'])) : 1;
                                } else {
                                    // For day-use, the sum is just the single price
                                    $slotData['price'] = $slotPricing->sum('final_price');
                                }
                                $slots[] = $slotData;
                            }
                        }
                    }

                    $results[] = [
                        'chalet' => $chaletModel,
                        'slots' => $slots,
                        'min_price' => $minPrice,
                        'min_total_price' => null,
                        'nights' => isset($searchParams['end_date']) && $searchParams['end_date'] ? Carbon::parse($searchParams['start_date'])->diffInDays(Carbon::parse($searchParams['end_date'])) : 1,
                        'booking_type' => $searchParams['booking_type'],
                    ];
                }

                // Debug: Log mapped search results
                \Log::info('Mapped search results for view', [
                    'count' => count($results),
                    'ids' => array_map(fn ($r) => $r['chalet'] instanceof \App\Models\Chalet ? $r['chalet']->id : null, $results),
                ]);

            } catch (\Exception $e) {
                // Log the error for debugging
                \Log::error('Search error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                return redirect()->back()->with('error', 'Error in search: '.$e->getMessage());
            }
        } else {
            // No search parameters - show active chalets without availability (legacy safe fallback)
            $allChalets = \App\Models\Chalet::where('status', \App\Enums\ChaletStatus::Active)
                ->with('media')
                ->get();

            $results = $allChalets->map(function ($ch) use ($bookingType) {
                return [
                    'chalet' => $ch,
                    'slots' => [],
                    'min_price' => null,
                    'min_total_price' => null,
                    'nights' => 1,
                    'booking_type' => $bookingType,
                ];
            })->values()->toArray();

            \Log::info('All chalets count (fallback)', ['count' => count($results)]);
        }

        return $this->view('chalets', [
            'results' => $results,
            'is_search' => $is_search,
            'checkin' => $request->input('check__in'),
            'checkout' => $request->input('check__out'),
            'bookingType' => $bookingType,
        ]);
    }

    // Room Detail page
    public function roomDetailSOne($slug)
    {
        $chalet = \App\Models\Chalet::where('slug', $slug)
            ->with([
                'amenities.media',
                'facilities.media',
                'media',
                'timeSlots',
                'owner',
                'reviews',
            ])
            ->firstOrFail();

        return $this->view('chalet-details', compact('chalet'));
    }

    // Contact
    public function contact()
    {
        return $this->view('contact');
    }

    /**
     * Show booking confirmation page
     */
    public function bookingConfirmation($bookingReference)
    {
        $booking = \App\Models\Booking::where('booking_reference', $bookingReference)
            ->with(['chalet', 'timeSlots'])
            ->first();

        if (! $booking) {
            return redirect()->route('index')->with('error', 'Booking not found.');
        }

        // Check if user is authorized to view this booking
        if (Auth::check() && $booking->user_id !== Auth::id()) {
            return redirect()->route('index')->with('error', 'You are not authorized to view this booking.');
        }

        return $this->view('booking-confirmation', [
            'booking' => $booking,
        ]);
    }

    /**
     * Handle contact form submission and send email
     */
    public function sendContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'msg' => 'required|string',
        ]);

        \Mail::raw($validated['msg'], function ($message) use ($validated) {
            $message->to('hadi.d.enG@gmail.com')
                ->subject('Moonlit Contact Form '.$validated['name'])
                ->from($validated['email'], $validated['name']);
        });

        if ($request->ajax()) {
            return response()->json(['success' => 'Thank You! Your message has been sent.']);
        }

        return back()->with('success', 'Thank You! Your message has been sent.');
    }
}
