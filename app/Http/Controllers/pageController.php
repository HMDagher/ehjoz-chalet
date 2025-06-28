<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ChaletAvailabilityService;
use App\Services\ChaletSearchService;
use Carbon\Carbon;

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
            ->get();

        $today = Carbon::create(2025, 6, 25);
        $featuredChaletsWithSlots = $featuredChalets->map(function ($chalet) use ($today) {
            $availability = new ChaletAvailabilityService($chalet);
            $slots = $chalet->timeSlots()->where('is_active', true)->get()->map(function ($slot) use ($availability, $today) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'price' => $availability->getPrice($today->format('Y-m-d'), $slot->id),
                ];
            });
            return [
                'chalet' => $chalet,
                'slots' => $slots,
            ];
        });

        return $this->view('index', [
            'chaletCount'    => $chaletCount,
            'bookingCount'   => $bookingCount,
            'customerCount'  => $customerCount,
            'featuredChaletsWithSlots' => $featuredChaletsWithSlots,
        ]);
    }
    // room three page
    public function chalets(Request $request, ChaletSearchService $chaletSearchService)
    {
        $results = [];
        $is_search = false;

        $checkin = $request->input('check__in');
        $checkout = $request->input('check__out');

        if ($request->filled('check__in') && $request->filled('check__out')) {
            $is_search = true;
            try {
                $startDate = Carbon::parse($checkin)->format('Y-m-d');
                $endDate = Carbon::parse($checkout)->format('Y-m-d');

                if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
                    return redirect()->back()->with('error', 'Check-out date must be after check-in date.');
                }

                $searchResults = $chaletSearchService->search($startDate, $endDate);

                if (!empty($searchResults)) {
                    $chaletIds = collect($searchResults)->pluck('chalet.id');
                    $chalets = \App\Models\Chalet::with('media')->findMany($chaletIds)->keyBy('id');

                    $results = array_map(function($result) use ($chalets) {
                        if (isset($result['chalet']['id']) && isset($chalets[$result['chalet']['id']])) {
                            $result['chalet'] = $chalets[$result['chalet']['id']];
                        }
                        return $result;
                    }, $searchResults);
                }

            } catch (\Exception $e) {
                
                return redirect()->back()->with('error', 'Invalid date format provided. Please use a valid date.');
            }
        } else {
            $allChalets = \App\Models\Chalet::where('status', \App\Enums\ChaletStatus::Active)->with(['media', 'timeSlots'])->latest()->get();
            $today = Carbon::today();
            $results = $allChalets->map(function ($chalet) use ($today) {
                $availability = new ChaletAvailabilityService($chalet);
                $slots = $chalet->timeSlots->where('is_active', true)->map(function ($slot) use ($availability, $today) {
                    return [
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $availability->getPrice($today->format('Y-m-d'), $slot->id),
                    ];
                });
                return [
                    'chalet' => $chalet,
                    'slots' => $slots,
                ];
            });
        }

        return $this->view('chalets', [
            'results' => $results,
            'is_search' => $is_search,
            'checkin' => $request->input('check__in'),
            'checkout' => $request->input('check__out'),
        ]);
    }

    // Room Detail page
    public function roomDetailSOne($slug)
    {
        $chalet = \App\Models\Chalet::where('slug', $slug)
            ->with([
                'amenities',
                'facilities',
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
}
