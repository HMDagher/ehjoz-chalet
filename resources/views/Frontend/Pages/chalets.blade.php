@extends('Frontend.Layouts.app')
@section('page_title', 'Room Style Three Template')

@section('content')
    @include('Frontend.Header.header')
    
    @php 
        $title = "Deluxe Room";
        $desc = "A step up from the standard room, often with better views, more space, and additional amenities.";
    @endphp
    @include('Frontend.Components.page-hero-with-search',compact('title','desc','checkin','checkout'))

    <!-- single rooms -->
    <div class="rts__section section__padding">
        <div class="container">
            <!-- row -->
            <div class="row g-30">
                @forelse($results as $result)
                    @php
                        $chaletModel = $result['chalet'];

                        if(!$chaletModel instanceof \App\Models\Chalet) continue;

                        $thumb = $chaletModel->getFirstMediaUrl('default') ?: asset('assets/images/room/4.webp');
                        $title = $chaletModel->name;
                        $desc = \Illuminate\Support\Str::limit(strip_tags($chaletModel->description), 150);
                        $chalet_slug = $chaletModel->slug;
                        $slots = $result['slots'] ?? [];

                        $price = 'Starting from $';
                        if (isset($result['min_price'])) {
                            $price = 'Starting from $' . number_format($result['min_price']);
                        } elseif (isset($result['min_total_price'])) {
                            $price = 'Total $' . number_format($result['min_total_price']);
                        } elseif ($chaletModel->base_price) {
                            $price = 'Starts from $' . number_format($chaletModel->base_price);
                        }

                    @endphp
                    <div class="col-lg-6">
                        @include('Frontend.Components.room-card-three', [
                            'thumb' => $thumb,
                            'price' => $price,
                            'title' => $title,
                            'desc' => $desc,
                            'chalet_slug' => $chalet_slug,
                            'slots' => $slots,
                            'chalet_url' => url($chalet_slug),
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
