@extends('Frontend.Layouts.app')
@section('page_title', 'Chalets for Rent in Lebanon | Find & Book Your Perfect Chalet')

@section('content')
    @include('Frontend.Header.header')
    
    @php 
        $title = "Chalets for Rent in Lebanon";
        $desc = "Browse our curated selection of chalets available for rent across Lebanon. Find the perfect chalet for your next getaway—whether for a weekend escape, family vacation, or special occasion. Search by date, amenities, and more!";
        $bookingType = $bookingType ?? request('booking_type', 'overnight');
    @endphp
    @include('Frontend.Components.page-hero-with-search',compact('title','desc','checkin','checkout','bookingType'))

    <!-- single rooms -->
    <div class="rts__section section__padding">
        <div class="container">
            <!-- row -->
            <div class="row g-30">
                @forelse($results as $result)
                    @php
                        $chaletModel = $result['chalet'];

                        if(!$chaletModel instanceof \App\Models\Chalet) continue;

                        $thumb = $chaletModel->getFirstMediaUrl('featured_image') ?: asset('assets/images/room/4.webp');
                        $title = $chaletModel->name;
                        $desc = \Illuminate\Support\Str::limit(strip_tags($chaletModel->description), 150);
                        $chalet_slug = $chaletModel->slug;
                        $slots = $result['slots'] ?? [];

                        // Pass price values to the component
                        $min_price = $result['min_price'] ?? null;
                        $min_total_price = $result['min_total_price'] ?? null;
                        
                        // Format display price - this is just for display in the header of the card
                        $price_display = '';
                        if (isset($result['booking_type']) && $result['booking_type'] === 'overnight' && isset($min_price)) {
                            $price_display = 'From $' . number_format($min_price) . ' / night';
                        } elseif (isset($result['booking_type']) && $result['booking_type'] === 'day-use' && isset($min_price)) {
                            $price_display = 'From $' . number_format($min_price);
                        } elseif (isset($min_price)) {
                            $price_display = 'From $' . number_format($min_price);
                        } elseif (isset($min_total_price)) {
                            $price_display = 'Total $' . number_format($min_total_price);
                        } elseif ($chaletModel->base_price) {
                            $price_display = 'From $' . number_format($chaletModel->base_price);
                        } else {
                            $price_display = 'Price on request';
                        }
                    @endphp
                    <div class="col-lg-6">
                        @include('Frontend.Components.room-card-three', [
                            'thumb' => $thumb,
                            'price' => $price_display,
                            'min_price' => $min_price,
                            'min_total_price' => $min_total_price,
                            'title' => $title,
                            'desc' => $desc,
                            'chalet_slug' => $chalet_slug,
                            'slots' => $slots,
                            'chalet_url' => url($chalet_slug) . ($is_search ? '?' . http_build_query([
                                'booking_type' => request('booking_type'),
                                'checkin' => request('check__in'),
                                'checkout' => request('check__out')
                            ]) : ''),
                            'max_adults' => $chaletModel->max_adults,
                            'max_children' => $chaletModel->max_children
                        ])
                    </div>
                @empty
                    <div class="col-12 text-center">
                        @if($is_search)
                            <h3>No Chalets Available</h3>
                            <p>We couldn't find any chalets available for the selected dates. Please try searching for different dates.</p>
                        @else
                            <h3>No Chalets Found</h3>
                            <p>There are currently no chalets to display. Please check back later.</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    <!-- single rooms end -->
    @include('Frontend.Footer.footer__common')
@endsection
