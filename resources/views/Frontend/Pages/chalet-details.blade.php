@extends('Frontend.Layouts.app')
@section('page_title', $chalet->name ?? 'Chalet Details')

@section('content')
    @include('Frontend.Header.header')
    
    @php 
        $media = $chalet->getMedia();
        $headerImage = $media->first();
        $galleryImages = $media->slice(1);
        $title = $chalet->name;
        $desc = $chalet->description;
    @endphp
    @include('Frontend.Components.page-hero-no-text', [
        'title' => $title,
        'desc' => $desc,
        'headerImage' => $headerImage ? $headerImage->getUrl() : null
    ])

    <!-- room details area -->
    <div class="rts__section section__padding">
        <div class="container">
            <div class="row g-5 sticky-wrap">
                <div class="col-xxl-8 col-xl-7">
                    <div class="room__details">
                        <span class="h4 price">{{ $chalet->timeSlots->first()?->price ?? 'N/A' }}$</span>
                        <h2 class="room__title">{{ $chalet->name }}</h2>
                        <div class="room__meta">
                            <span><i class="flaticon-construction"></i>{{ $chalet->bedrooms_count ?? '-' }} Bedrooms, {{ $chalet->bathrooms_count ?? '-' }} Bathrooms</span>
                            <span><i class="flaticon-user"></i>{{ $chalet->max_adults + $chalet->max_children }} Person</span>
                        </div>
                        <div class="mb-2"><strong>Address:</strong> {{ $chalet->address }}, {{ $chalet->city }}</div>
                        <div class="mb-2"><strong>Owner:</strong> {{ $chalet->owner?->name ?? '-' }}</div>
                        <div class="mb-2"><strong>Check-in Instructions:</strong> {{ $chalet->check_in_instructions }}</div>
                        <div class="mb-2"><strong>House Rules:</strong> {{ $chalet->house_rules }}</div>
                        <div class="mb-2"><strong>Cancellation Policy:</strong> {{ $chalet->cancellation_policy }}</div>
                        <div class="mb-2"><strong>Status:</strong> {{ $chalet->status->value ?? '-' }}</div>
                        <div class="mb-2"><strong>Average Rating:</strong> {{ $chalet->average_rating ?? '-' }} ({{ $chalet->total_reviews ?? 0 }} reviews)</div>
                        <div class="mb-2"><strong>Social:</strong>
                            @if($chalet->facebook_url)
                                <a href="{{ $chalet->facebook_url }}" target="_blank">Facebook</a>
                            @endif
                            @if($chalet->instagram_url)
                                <a href="{{ $chalet->instagram_url }}" target="_blank">Instagram</a>
                            @endif
                            @if($chalet->website_url)
                                <a href="{{ $chalet->website_url }}" target="_blank">Website</a>
                            @endif
                            @if($chalet->whatsapp_number)
                                <a href="https://wa.me/{{ $chalet->whatsapp_number }}" target="_blank">WhatsApp</a>
                            @endif
                        </div>
                        <p class="mt-3">{{ $chalet->description }}</p>
                        <span class="h4 d-block mb-30">Gallery</span>
                        <div class="room__image__group row row-cols-md-2 row-cols-sm-1 mt-30 mb-50 gap-4 gap-md-0">
                            @foreach($galleryImages as $media)
                                <div class="room__image__item">
                                    <img class="rounded-2" src="{{ $media->getUrl() }}" alt="{{ $chalet->name }}">
                                </div>
                            @endforeach
                        </div>
                        <span class="h4 d-block mb-30">Amenities</span>
                        <div class="room__amenity mb-50">
                            <div class="group__row">
                                @foreach($chalet->amenities as $amenity)
                                    <div class="single__item">
                                        <span>{{ $amenity->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <span class="h4 d-block mb-30">Facilities</span>
                        <div class="room__feature mb-30">
                            <div class="group__row">
                                <ul class="list__item">
                                    @foreach($chalet->facilities as $facility)
                                        <li>{{ $facility->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <span class="h4 d-block mb-30">Available Time Slots</span>
                        <div class="mb-4">
                            @forelse($chalet->timeSlots as $slot)
                                <div>
                                    <strong>{{ $slot->name }}</strong>: {{ $slot->start_time }} - {{ $slot->end_time }} ({{ $slot->duration_hours }}h) | Price: {{ $slot->price }}$
                                </div>
                            @empty
                                <div>No time slots available.</div>
                            @endforelse
                        </div>
                        <span class="h4 d-block mb-30">Reviews</span>
                        <div class="mb-4">
                            @forelse($chalet->reviews as $review)
                                <div class="mb-2">
                                    <strong>{{ $review->user?->name ?? 'Guest' }}</strong>:
                                    <span>{{ $review->rating }}/5</span>
                                    <p>{{ $review->comment }}</p>
                                </div>
                            @empty
                                <div>No reviews yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xxl-4 col-xl-5 sticky-item">
                    <div class="rts__booking__form has__background is__room__details">
                        <form action="#" method="post" class="advance__search">
                            <h5 class="pt-0">Book Your Stay</h5>
                            <div class="advance__search__wrapper">
                                <div class="query__input wow fadeInUp">
                                    <label for="check__in" class="query__label">Check In</label>
                                    <div class="query__input__position">
                                        <input type="text" id="check__in" name="check__in" placeholder="15 Jun 2024" required>
                                        <div class="query__input__icon">
                                            <i class="flaticon-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="query__input wow fadeInUp" data-wow-delay=".3s">
                                    <label for="check__out" class="query__label">Check Out</label>
                                    <div class="query__input__position">
                                        <input type="text" id="check__out" name="check__out" placeholder="15 May 2024" required>
                                        <div class="query__input__icon">
                                            <i class="flaticon-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="query__input wow fadeInUp" data-wow-delay=".4s">
                                    <label for="adult" class="query__label">Adult</label>
                                    <div class="query__input__position">
                                        <select name="adult" id="adult" class="form-select">
                                            @for($i = 1; $i <= $chalet->max_adults; $i++)
                                                <option value="{{ $i }}">{{ $i }} Person</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="query__input wow fadeInUp" data-wow-delay=".5s">
                                    <label for="child" class="query__label">Child</label>
                                    <div class="query__input__position">
                                        <select name="child" id="child" class="form-select">
                                            @for($i = 0; $i <= $chalet->max_children; $i++)
                                                <option value="{{ $i }}">{{ $i }} Child</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('Frontend.Footer.footer__common')
@endsection
