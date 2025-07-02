<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ChaletAvailabilityService;
use App\Services\ChaletSearchService;
use Carbon\Carbon;
use App\Services\ChaletAvailabilityChecker;

class pageController extends baseController
{
    // homepage one
    public function index()
    {
        $chaletCount = \App\Models\Chalet::count();
        $bookingCount = \App\Models\Booking::count();
        $customerCount = \App\Models\User::role('customer')->count();

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
            'slots_count' => $featuredChalets->mapWithKeys(fn($c) => [$c->id => $c->timeSlots->count()])->toArray(),
        ]);

        $today = Carbon::today()->format('Y-m-d');
        $featuredChaletsWithSlots = [];

        foreach ($featuredChalets as $chalet) {
            $availabilityChecker = new ChaletAvailabilityChecker($chalet);
            
            // Get both day-use and overnight slots
            $dayUseSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', false)
                ->filter(function($slot) use ($availabilityChecker, $today) {
                    return $availabilityChecker->isDayUseSlotAvailable($today, $slot->id);
                })
                ->map(function ($slot) use ($availabilityChecker, $today) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                        'price' => $availabilityChecker->calculateDayUsePrice($today, $slot->id),
                    ];
                })->values()->toArray();
                
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $overnightSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', true)
                ->filter(function($slot) use ($availabilityChecker, $today, $tomorrow) {
                    return $availabilityChecker->isOvernightSlotAvailable($today, $tomorrow, $slot->id);
                })
                ->map(function ($slot) use ($availabilityChecker, $today, $tomorrow) {
                    $priceData = $availabilityChecker->calculateOvernightPrice($today, $tomorrow, $slot->id);
            return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $priceData['price_per_night'],
                    ];
                })->values()->toArray();
                
            $allSlots = array_merge($dayUseSlots, $overnightSlots);
            
            // Debug: Log available slots for each featured chalet
            \Log::info('Featured chalet available slots', [
                'chalet_id' => $chalet->id,
                'day_use_slots' => $dayUseSlots,
                'overnight_slots' => $overnightSlots,
                'all_slots_count' => count($allSlots),
            ]);
            
            if (!empty($allSlots)) {
                $featuredChaletsWithSlots[] = [
                'chalet' => $chalet,
                    'slots' => $allSlots,
            ];
            }
        }

        // Debug: Log after filtering for available slots
        \Log::info('Featured chalets with available slots', [
            'count' => count($featuredChaletsWithSlots),
            'ids' => array_map(fn($c) => $c['chalet']->id, $featuredChaletsWithSlots),
        ]);

        return $this->view('index', [
            'chaletCount'    => $chaletCount,
            'bookingCount'   => $bookingCount,
            'customerCount'  => $customerCount,
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

        $chaletSearchService = new ChaletSearchService();

        if ($request->filled('check__in')) {
            $is_search = true;
            try {
                // Debug information
                \Log::info('Search parameters', [
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'bookingType' => $bookingType
                ]);
                
                // If it's a day-use booking, end date is not needed
                if ($bookingType === 'day-use') {
                    $results = $chaletSearchService->searchChalets(
                        $checkin,
                        null,
                        'day-use'
                    );
                } else {
                    // For overnight bookings, ensure checkout date is valid
                    if ($request->filled('check__out')) {
                        $startDate = Carbon::parse($checkin);
                        $endDate = Carbon::parse($checkout);
                        
                        if ($startDate->gt($endDate)) {
                    return redirect()->back()->with('error', 'Check-out date must be after check-in date.');
                }

                        $results = $chaletSearchService->searchChalets(
                            $checkin,
                            $checkout,
                            'overnight'
                        );
                    } else {
                        // No checkout date provided, use default (next day)
                        $results = $chaletSearchService->searchChalets(
                            $checkin,
                            null,
                            'overnight'
                        );
                    }
                }
                
                // Debug: Log raw search results
                \Log::info('Raw search results', [
                    'count' => count($results),
                    'ids' => array_map(fn($r) => $r['chalet']['id'] ?? null, $results),
                ]);

                // For search results, we need to get the full chalet models with media
                if (!empty($results)) {
                    $chaletIds = collect($results)->pluck('chalet.id');
                    $chalets = \App\Models\Chalet::with('media')->findMany($chaletIds)->keyBy('id');

                    foreach ($results as &$result) {
                        if (isset($result['chalet']['id']) && isset($chalets[$result['chalet']['id']])) {
                            // Keep the slots and price data while replacing the chalet model
                            $slots = $result['slots'] ?? [];
                            $minPrice = $result['min_price'] ?? 0;
                            $minTotalPrice = $result['min_total_price'] ?? 0;
                            $nights = $result['nights'] ?? 1;
                            $bookingType = $result['booking_type'] ?? 'overnight';
                            
                            $result['chalet'] = $chalets[$result['chalet']['id']];
                            $result['slots'] = $slots;
                            $result['min_price'] = $minPrice;
                            $result['min_total_price'] = $minTotalPrice;
                            $result['nights'] = $nights;
                            $result['booking_type'] = $bookingType;
                        }
                    }
                }

                // Debug: Log filtered search results
                \Log::info('Filtered search results', [
                    'count' => count($results),
                    'ids' => array_map(fn($r) => $r['chalet'] instanceof \App\Models\Chalet ? $r['chalet']->id : null, $results),
                ]);

            } catch (\Exception $e) {
                // Log the error for debugging
                \Log::error('Search error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return redirect()->back()->with('error', 'Error in search: ' . $e->getMessage());
            }
        } else {
            // No search parameters - show all chalets with available slots
            $results = $chaletSearchService->getAllChalets();
            \Log::info('All chalets count', ['count' => count($results)]);
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

        if (!$booking) {
            return redirect()->route('index')->with('error', 'Booking not found.');
        }

        // Check if user is authorized to view this booking
        if (Auth::check() && $booking->user_id !== Auth::id()) {
            return redirect()->route('index')->with('error', 'You are not authorized to view this booking.');
        }

        return $this->view('booking-confirmation', [
            'booking' => $booking
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
                ->subject('Moonlit Contact Form ' . $validated['name'])
                ->from($validated['email'], $validated['name']);
        });

        if ($request->ajax()) {
            return response()->json(['success' => 'Thank You! Your message has been sent.']);
        }

        return back()->with('success', 'Thank You! Your message has been sent.');
    }
}
