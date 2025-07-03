@extends('Frontend.Layouts.app')
@section('page_title', 'Lebanon Chalets | Book the Best Chalets for Rent Online')

@section('content')

    @include('Frontend.Header.header-five')
    <!-- banner area -->
     <div class="rts__section banner__area is__home__two banner__height banner__center">
        <div class="banner__content">
            <div class="banner__slider__image">
                <img src="{{asset('assets/images/banner/slides-2.webp')}}" alt="">
            </div>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="banner__slide__content">
                            <h1 class="wow fadeInUp">Discover & Book Chalets in Lebanon</h1>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>
    <!-- banner area end -->

    <!-- advance search -->
    @php 
        $class = "is__home__one wow fadeInUp";
        $attr = "data-wow-delay='.5s'";
    @endphp
    @include('Frontend.Partials.advance__search',compact('class','attr'))
    <!-- advance search end -->

    <!-- our room -->
    <div class="rts__section section__padding">
        <div class="container">
            <div class="row">
                <div class="section__wrapper mb-40  wow fadeInUp">
                    <div class="section__content__left">
                        <span class="h6 subtitle__icon__two d-block wow fadeInUp">Chalets</span>
                        <h2 class="content__title h2 lh-1">Our Chalets</h2>
                    </div>
                    <div class="section__content__right">
                        <p>Our chalets offer a harmonious blend of comfort and elegance, designed to provide an exceptional stay for every guest. Each chalet features plush bedding, high-quality linens, and a selection of pillows to ensure a restful night's sleep.</p>
                    </div>
                </div>
            </div>
            <!-- row end -->
            <div class="row">
                <div class="room__slider overflow-hidden wow fadeInUp" data-wow-delay=".5s">
                    <div class="swiper-wrapper">
                        <!-- single room slider -->
                        @forelse($featuredChaletsWithSlots as $chaletData)
                            @php
                                $chalet = $chaletData['chalet'];
                                $slots = $chaletData['slots'] ?? [];
                                
                                // Get the lowest price from the slots
                                $lowestPrice = null;
                                foreach ($slots as $slot) {
                                    $slotPrice = $slot['price'] ?? 0;
                                    if ($lowestPrice === null || $slotPrice < $lowestPrice) {
                                        $lowestPrice = $slotPrice;
                                    }
                                }
                                
                                $priceDisplay = '';
                                if ($lowestPrice) {
                                    $priceDisplay = 'From $' . number_format($lowestPrice);
                                } elseif ($chalet->base_price) {
                                    $priceDisplay = 'From $' . number_format($chalet->base_price);
                                } else {
                                    $priceDisplay = 'Price on request';
                                }
                            @endphp
                            <div class="swiper-slide">
                                <div class="room__slide__box radius-6">
                                    <div class="room__thumbnail jara-mask-2 jarallax">
                                        @if($chalet->getFirstMediaUrl('featured_image'))
                                            <img height="585" width="420" class="radius-6 jarallax-img" src="{{ $chalet->getFirstMediaUrl('featured_image') }}" alt="{{ $chalet->name }}">
                                        @else
                                            <img height="585" width="420" class="radius-6 jarallax-img" src="{{asset('assets/images/room/4.webp')}}" alt="{{ $chalet->name }}">
                                        @endif
                                    </div>
                                    <div class="room__content">
                                        <a href="{{ url($chalet->slug) }}" class="room__title">
                                            <h5>{{ $chalet->name }}</h5>
                                        </a>
                                        <div class="room__content__meta">
                                            <span><i class="flaticon-construction"></i> {{ $chalet->bedrooms_count ?? '-' }} Bedrooms</span>
                                            <span><i class="flaticon-user"></i> {{ $chalet->max_adults + $chalet->max_children }} Guests</span>
                                            <span><i class="flaticon-tag"></i> {{ $priceDisplay }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="swiper-slide">
                                <div class="room__slide__box radius-6">
                                    <div class="room__content">
                                        <h5>No featured chalets available at the moment.</h5>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                        <!-- single room slider end -->
                    </div>
                </div>
                <!-- pagination button -->
                <div class="rts__pagination">
                    <div class="rts-pagination"></div>
                </div>
               <!-- pagination button end -->
            </div>
        </div>
    </div>
    <!-- our room end -->

    @include('Frontend.Footer.footer__two')
@endsection