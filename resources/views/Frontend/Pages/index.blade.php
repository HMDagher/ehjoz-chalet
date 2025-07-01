@extends('Frontend.Layouts.app')
@section('page_title', 'Hotel and Resort Laravel 12 Template')

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
                            <h1 class="wow fadeInUp">Luxury Stay Chalet Experience Comfort & Elegance</h1>
                            <div class="banner__slide__content__info">
                                <div class="item wow fadeInUp" data-wow-delay=".3s">
                                    <span class="h2 d-block">{{ $chaletCount }}</span>
                                    <p>Chalets</p>
                                </div>
                                <div class="item wow fadeInUp" data-wow-delay=".5s">
                                    <span class="h2 d-block">{{ $bookingCount }}</span>
                                    <p>Bookings</p>
                                </div>
                                <div class="item wow fadeInUp" data-wow-delay=".7s">
                                    <span class="h2 d-block">{{ $customerCount }}</span>
                                    <p>Customers</p>
                                </div>
                            </div>
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
                                        @if($chalet->getFirstMediaUrl('default'))
                                            <img height="585" width="420" class="radius-6 jarallax-img" src="{{ $chalet->getFirstMediaUrl('default') }}" alt="{{ $chalet->name }}">
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
                                        <ul class="list-unstyled mb-0 mt-15">
                                            @forelse($chaletData['slots'] as $slot)
                                                <li class="py-0 px-1 rounded bg-dark bg-opacity-75 d-flex align-items-center border border-secondary">
                                                    <div class="text-white">
                                                        <p class="mb-0 fw-bold small">{{ $slot['name'] }} <span class="small">({{ \Carbon\Carbon::parse($slot['start_time'])->format('g:i A') }} - {{ \Carbon\Carbon::parse($slot['end_time'])->format('g:i A') }}, {{ $slot['duration_hours'] }} hrs)</span></p>
                                                    </div>
                                                    <span class="ms-auto small mb-0 text-white fw-bold">
                                                        {{ number_format($slot['price']) }}$
                                                    </span>
                                                </li>
                                            @empty
                                                <li class="py-0 px-1 rounded bg-dark bg-opacity-75 d-flex align-items-center border border-secondary">
                                                    <div class="text-white">
                                                        <p class="mb-0 fw-bold small">No timeslots available</p>
                                                    </div>
                                                    <span class="ms-auto small mb-0 text-white fw-bold">
                                                        N/A
                                                    </span>
                                                </li>
                                            @endforelse
                                        </ul>
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
     

    <!-- client testimonial -->
     <div class="rts__section client__testimonial is__home__two has__background has__shape py-90">
        <div class="section__shape">
            <img src="{{asset('assets/images/shape/testimonial__two.png')}}" alt="">
            <div class="shape__two">
                <img src="{{asset('assets/images/shape/testimonial__two-2.png')}}" alt="">
            </div>
        </div>
        <div class="container">
            <div class="row justify-content-center text-center mb-40">
                <div class="col-lg-6 wow fadeInUp">
                    <div class="section__topbar is__home__two">
                        <span class="h6 subtitle__icon__three mx-auto text-white">Testimonial</span>
                        <h2 class="section__title text-white">What Our Customers Say</h2>
                    </div>
                </div>
            </div>
            <div class="row position-relative justify-content-center ">
                <div class="col-lg-10">
                    <div class="testimonial__slider overflow-hidden wow fadeInUp" data-wow-delay=".3s">
                        <div class="swiper-wrapper">
                            <!-- single slider item -->
                            <div class="swiper-slide">
                                <div class="single__slider__item is__home ">
                                    <div class="slider__rating mb-30">
                                        <i class="flaticon-star"></i>
                                        <i class="flaticon-star"></i>
                                        <i class="flaticon-star"></i>
                                        <i class="flaticon-star"></i>
                                        <i class="flaticon-star-sharp-half-stroke"></i>
                                    </div>
                                    <span class="slider__text d-block">Choosing Bokinn was one of the best decisions we've ever made. They have proven to be a reliable and innovative partner, always ready to tackle new challenges with and expertise.Their commitment to and delivering tailored.</span>
                                    <div class="slider__author__info">
                                        <div class="slider__author__info__image">
                                            <img src="{{asset('assets/images/author/author-2.webp')}}" alt="">
                                        </div>
                                        <div class="slider__author__info__content">
                                            <h6 class="mb-0">Alex Smith</h6>
                                            <span>Chalet name</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- single slider item end -->
                        </div>
                    </div>
                </div>
                <div class="full__width__nav">
                    <div class="rts__slide">
                        <div class="next slider-button-prev">
                            <svg width="41" height="22" viewBox="0 0 41 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.25536 9.75546H39.0408C39.7335 9.75546 40.2931 10.3151 40.2931 11.0078C40.2931 11.7005 39.7335 12.2601 39.0408 12.2601H4.28054L11.8807 19.8603C12.3699 20.3495 12.3699 21.1439 11.8807 21.6331C11.3915 22.1223 10.597 22.1223 10.1078 21.6331L0.366985 11.8923C0.00693893 11.5322 -0.098732 10.9961 0.0969467 10.5264C0.292625 10.0607 0.750515 9.75546 1.25536 9.75546Z" fill="#65676B"/>
                                <path d="M11.0079 0.0028038C11.3288 0.0028038 11.6497 0.124125 11.8924 0.370679C12.3816 0.859874 12.3816 1.65432 11.8924 2.14352L2.13979 11.8961C1.6506 12.3853 0.856151 12.3853 0.366956 11.8961C-0.122239 11.4069 -0.122239 10.6125 0.366956 10.1233L10.1195 0.370679C10.3661 0.124125 10.687 0.0028038 11.0079 0.0028038Z" fill="#65676B"/>
                            </svg> 
                        </div>
                    </div>
                    <div class="rts__slide">
                        <div class="prev slider-button-next">
                            <svg width="41" height="22" viewBox="0 0 41 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M39.0374 12.2499L1.25198 12.245C0.55928 12.2449 -0.000286104 11.6852 -0.000196778 10.9925C-0.000107452 10.2998 0.559603 9.74024 1.2523 9.74033L36.0125 9.74481L28.4134 2.14371C27.9242 1.65445 27.9243 0.859997 28.4136 0.370865C28.9029 -0.118267 29.6973 -0.118164 30.1864 0.371094L39.926 10.1132C40.286 10.4733 40.3916 11.0095 40.1959 11.4791C40.0001 11.9447 39.5422 12.2499 39.0374 12.2499Z" fill="#65676B"/>
                                <path d="M29.2835 22.0013C28.9626 22.0012 28.6417 21.8799 28.3991 21.6333C27.9099 21.144 27.91 20.3496 28.3993 19.8604L38.1531 10.1091C38.6424 9.61998 39.4368 9.62008 39.926 10.1093C40.4151 10.5986 40.415 11.393 39.9257 11.8822L30.1719 21.6335C29.9253 21.88 29.6044 22.0013 29.2835 22.0013Z" fill="#65676B"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>
    <!-- client testimonial end -->

    <!-- video section start -->
     <div class="rts__section section__padding video has__shape">
        <div class="section__shape">
            <div class="shape__1">
                <img src="{{asset('assets/images/shape/video-1.svg')}}" alt="">
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="video__area position-relative wow fadeInUp">
                        <div class="video__area__image jara-mask-2 jarallax">
                            <img class="radius-10 jarallax-img" src="{{asset('assets/images/video/video-bg-2.webp')}}" alt="">
                        </div>
                        <div class="video--spinner__wrapper ">
                            <div class="rts__circle">
                                <svg class="spinner" viewBox="0 0 100 100">
                                    <defs>
                                        <path id="circle-2" d="M50,50 m-37,0a37,37 0 1,1 74,0a37,37 0 1,1 -74,0"></path>
                                    </defs>
                                    <text>
                                        <textPath xlink:href="#circle-2">Watch Now * Watch Now * Watch Full Video *</textPath>
                                    </text>
                                </svg>
                                <div class="rts__circle--icon">
                                    <a href="https://www.youtube.com/watch?v=qOwxqRGHy5Q" class="video-play">
                                        <i class="flaticon-play"></i>

                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>
    <!-- video section end -->

    <!-- newsletter section -->
    <div class="rts__section section__padding">
        <div class="container">
            <div class="row">
                <div class="footer__newsletter is__separate wow fadeInUp">
                    <span class="h2 mb-0">Join Our Newsletter</span>
                    <div class="rts__form">
                        <form action="#" method="post">
                            <input type="email" name="email" id="subscription" placeholder="Enter your mail" required>
                            <button type="submit" >Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- newsletter section end -->

    @include('Frontend.Footer.footer__two')
@endsection