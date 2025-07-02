@extends('Frontend.Layouts.app')
@section('page_title', $chalet->name ?? 'Chalet Details')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @include('Frontend.Header.header')
    
        @php 
        $headerImage = $chalet->getFirstMediaUrl('featured_image');
        $galleryImages = $chalet->getMedia('default');
        $title = $chalet->name;
        $desc = $chalet->description;
    @endphp
    @include('Frontend.Components.page-hero-no-text', [
        'title' => $title,
        'desc' => $desc,
        'headerImage' => $headerImage ? $headerImage : null
    ])

    <!-- room details area -->
    <div class="rts__section section__padding">
        <div class="container">
            <div class="row g-5 sticky-wrap">
                <div class="col-xxl-8 col-xl-7">
                    <div class="room__details">
                        <h2 class="room__title">{{ $chalet->name }}</h2>
                        <div class="room__meta">
                            <span><i class="flaticon-construction"></i>{{ $chalet->bedrooms_count ?? '-' }} Bedrooms, {{ $chalet->bathrooms_count ?? '-' }} Bathrooms</span>
                            <span><i class="flaticon-user"></i>{{ $chalet->max_adults + $chalet->max_children }} Person</span>
                        </div>

                        @if($chalet->address || $chalet->city)
                            <div class="mb-2"><strong>Address:</strong> {{ $chalet->address }}, {{ $chalet->city }}</div>
                        @endif

                        @if($chalet->check_in_instructions)
                            <div class="mb-2"><strong>Check-in Instructions:</strong> {{ $chalet->check_in_instructions }}</div>
                        @endif

                        @if($chalet->house_rules)
                            <div class="mb-2"><strong>House Rules:</strong> {{ $chalet->house_rules }}</div>
                        @endif

                        @if($chalet->cancellation_policy)
                            <div class="mb-2"><strong>Cancellation Policy:</strong> {{ $chalet->cancellation_policy }}</div>
                        @endif

                        @if($chalet->facebook_url || $chalet->instagram_url || $chalet->website_url || $chalet->whatsapp_number)
                            <div class="mb-2"><strong>Social:</strong>
                                @if($chalet->facebook_url)
                                    <a href="{{ $chalet->facebook_url }}" target="_blank" class="me-2">Facebook</a>
                                @endif
                                @if($chalet->instagram_url)
                                    <a href="{{ $chalet->instagram_url }}" target="_blank" class="me-2">Instagram</a>
                                @endif
                                @if($chalet->website_url)
                                    <a href="{{ $chalet->website_url }}" target="_blank" class="me-2">Website</a>
                                @endif
                                @if($chalet->whatsapp_number)
                                    <a href="https://wa.me/{{ $chalet->whatsapp_number }}" target="_blank">WhatsApp</a>
                                @endif
                            </div>
                        @endif

                        @if($chalet->description)
                            <div class="mt-3">{!! $chalet->description !!}</div>
                        @endif

                        @if($galleryImages->isNotEmpty())
                            <span class="h4 d-block mb-30">Gallery</span>
                            <div class="room__image__group gallery row row-cols-md-2 row-cols-sm-1 mt-30 mb-50 gap-4 gap-md-0">
                                @foreach($galleryImages as $media)
                                    <div class="room__image__item" @if($loop->index >= 2) style="display: none;" @endif>
                                        <a href="{{ $media->getUrl() }}" title="{{ $chalet->name }}" @if($loop->index == 1 && $galleryImages->count() > 2) style="position: relative; display: block;" @endif>
                                            <img class="rounded-2" src="{{ $media->getUrl() }}" alt="{{ $chalet->name }}">
                                            @if($loop->index == 1 && $galleryImages->count() > 2)
                                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; border-radius: 0.5rem; cursor: pointer;">
                                                    <span>+{{ $galleryImages->count() - 2 }} more</span>
                                                </div>
                                            @endif
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($chalet->amenities->isNotEmpty())
                            <span class="h4 d-block mb-30">Amenities</span>
                            <div class="room__amenity mb-50">
                                <div class="group__row">
                                    @foreach($chalet->amenities as $amenity)
                                        <div class="single__item" style="padding: 5px 0;">
                                            @if($amenity->hasMedia())
                                                <img src="{{ $amenity->getFirstMediaUrl() }}" alt="{{ $amenity->name }}" style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;">
                                            @endif
                                            <span>{{ $amenity->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($chalet->facilities->isNotEmpty())
                            <span class="h4 d-block mb-30">Facilities</span>
                            <div class="room__amenity mb-30">
                                <div class="group__row">
                                    @foreach($chalet->facilities as $facility)
                                        <div class="single__item" style="padding: 5px 0;">
                                            @if($facility->hasMedia())
                                                <img src="{{ $facility->getFirstMediaUrl() }}" alt="{{ $facility->name }}" style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;">
                                            @endif
                                            <span>{{ $facility->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($chalet->latitude && $chalet->longitude)
                        <div class="col-lg-12">
                            <div class="contact__map mb-4">
                                <iframe
                                    class="w-100"
                                    height="350"
                                    style="border:0;"
                                    loading="lazy"
                                    allowfullscreen
                                    referrerpolicy="no-referrer-when-downgrade"
                                    src="https://maps.google.com/maps?q={{ $chalet->latitude }},{{ $chalet->longitude }}&amp;z=15&amp;output=embed">
                                </iframe>
                            </div>
                        </div>
                        @endif

                        <span class="h4 d-block mb-30">Reviews</span>
                        @if($chalet->reviews->isNotEmpty())
                            <span class="mb-2"><strong>Average Rating:</strong> {{ $chalet->average_rating ?? '-' }} ({{ $chalet->total_reviews ?? 0 }} reviews)</span>
                        @endif
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
                        <form action="#" method="post" class="advance__search" id="booking-form">
                            <h5 class="pt-0">Book Your Stay</h5>
                            <div class="advance__search__wrapper">
                                <!-- booking type input -->
                                <div class="query__input wow fadeInUp">
                                    <label for="booking_type" class="query__label">Booking Type</label>
                                    <select name="booking_type" id="booking_type" class="form-select">
                                        <option value="day-use" {{ request('booking_type') == 'day-use' ? 'selected' : '' }}>Day Use</option>
                                        <option value="overnight" {{ request('booking_type') == 'overnight' ? 'selected' : '' }}>Overnight</option>
                                    </select>
                                    <div class="query__input__icon">
                                        <i class="flaticon-calendar"></i>
                                    </div>
                                </div>
                                <!-- booking type input end -->

                                <div class="query__input wow fadeInUp">
                                    <label for="check__in" class="query__label">Check In</label>
                                    <div class="query__input__position">
                                        <input type="text" id="check__in" name="check__in" placeholder="{{ now()->format('d M Y') }}" value="{{ request('checkin') ?? request('check__in') }}" required>
                                        <div class="query__input__icon">
                                            <i class="flaticon-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="query__input checkout-field wow fadeInUp" data-wow-delay=".3s">
                                    <label for="check__out" class="query__label">Check Out</label>
                                    <div class="query__input__position">
                                        <input type="text" id="check__out" name="check__out" placeholder="{{ now()->addDay()->format('d M Y') }}" value="{{ request('checkout') ?? request('check__out') }}">
                                        <div class="query__input__icon">
                                            <i class="flaticon-calendar"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Available slots container for day-use -->
                                <div id="available-slots-container" class="wow fadeInUp" data-wow-delay=".4s" style="display: none;">
                                    <label class="query__label">Available Time Slots</label>
                                    <div id="available-slots-list" class="mb-3">
                                        <!-- Slots will be populated here -->
                                    </div>
                                    <div id="selected-slots-summary" class="mb-3" style="display: none;">
                                        <strong>Selected:</strong> <span id="selected-slots-text"></span>
                                        <br><strong>Total:</strong> $<span id="total-price">0</span>
                                    </div>
                                </div>

                                <!-- Price summary for overnight -->
                                <div id="overnight-price-summary" class="wow fadeInUp" data-wow-delay=".4s" style="display: none;">
                                    <div class="mb-3">
                                        <strong>Price per night:</strong> $<span id="price-per-night">0</span>
                                        <br><strong>Total for stay:</strong> $<span id="overnight-total-price">0</span>
                                    </div>
                                </div>

                                <!-- Book button -->
                                <button type="submit" class="theme-btn btn-style fill no-border search__btn wow fadeInUp" data-wow-delay=".5s" id="book-button" disabled>
                                    <span>Check Availability</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('Frontend.Footer.footer__common')
@endsection

@section('script')
    <script>
        // Simple jQuery test
        console.log('jQuery version:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'Not loaded');
        
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for jQuery to be available
            if (typeof jQuery === 'undefined') {
                console.error('jQuery is not loaded');
                return;
            }

            const $ = jQuery;
            console.log('jQuery is loaded, version:', $.fn.jquery);
            
            const chaletSlug = '{{ $chalet->slug }}';
            const chaletId = {{ $chalet->id }};
            let selectedSlots = [];
            let availableSlots = [];

            // Debug: Log URL parameters
            console.log('URL Parameters:', {
                booking_type: '{{ request("booking_type") }}',
                checkin: '{{ request("checkin") }}',
                checkout: '{{ request("checkout") }}'
            });

            // Use existing datepicker from main.js
            $("#check__in, #check__out").datepicker({
                dateFormat: "dd-mm-yy",
                duration: "fast",
                minDate: 0
            });

            // When check-in changes, update checkout's minDate (from main.js)
            $('#check__in').on('change', function() {
                console.log('Check-in changed:', $(this).val()); // Debug log
                var checkInDate = $(this).datepicker('getDate');
                if (checkInDate) {
                    // Add one day to check-in for minDate
                    var minCheckout = new Date(checkInDate.getTime());
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    $('#check__out').datepicker('option', 'minDate', minCheckout);
                    // If checkout is before or same as check-in, clear it
                    var checkOutDate = $('#check__out').datepicker('getDate');
                    if (!checkOutDate || checkOutDate <= checkInDate) {
                        $('#check__out').val('');
                    }
                } else {
                    // If no check-in, allow any checkout
                    $('#check__out').datepicker('option', 'minDate', null);
                }
                
                // Clear availability data when dates change
                clearAvailabilityData();
                checkAvailability();
            });

            // Handle checkout date change
            $('#check__out').on('change', function() {
                console.log('Check-out changed:', $(this).val()); // Debug log
                clearAvailabilityData();
                checkAvailability();
            });

            // Handle booking type change (from main.js)
            $("#booking_type").on("change", function() {
                console.log('Booking type changed:', $(this).val()); // Debug log
                toggleCheckoutField();
                clearAvailabilityData();
                checkAvailability();
            });

            // Handle slot selection for day-use
            $(document).on("change", ".slot-checkbox", function() {
                updateSelectedSlots();
            });

            // Handle form submission
            $("#booking-form").on("submit", function(e) {
                e.preventDefault();
                submitBooking();
            });

            // Initial setup
            toggleCheckoutField();
            if ($("#check__in").val()) {
                console.log('Initial check-in value:', $("#check__in").val()); // Debug log
                checkAvailability();
            }

            function toggleCheckoutField() {
                var bookingType = $("#booking_type").val();
                if (bookingType === "day-use") {
                    $(".checkout-field").hide();
                    $("#check__out").prop("required", false);
                } else {
                    $(".checkout-field").show();
                    $("#check__out").prop("required", true);
                }
            }

            function clearAvailabilityData() {
                $("#available-slots-container").hide();
                $("#overnight-price-summary").hide();
                $("#book-button").prop("disabled", true).text("Check Availability");
                selectedSlots = [];
                availableSlots = [];
            }

            function checkAvailability() {
                const bookingType = $("#booking_type").val();
                const startDate = $("#check__in").val();
                const endDate = $("#check__out").val();

                console.log('Checking availability:', { bookingType, startDate, endDate }); // Debug log

                if (!startDate) {
                    console.log('No start date provided'); // Debug log
                    return;
                }

                // Convert date format from dd-mm-yyyy to yyyy-mm-dd
                const formattedStartDate = convertDateFormat(startDate);
                const formattedEndDate = endDate ? convertDateFormat(endDate) : null;

                console.log('Formatted dates:', { formattedStartDate, formattedEndDate }); // Debug log

                $("#book-button").prop("disabled", true).text("Checking...");

                $.ajax({
                    url: `/api/chalet/${chaletSlug}/availability`,
                    method: 'GET',
                    data: {
                        booking_type: bookingType,
                        start_date: formattedStartDate,
                        end_date: formattedEndDate
                    },
                    success: function(response) {
                        console.log('Availability response:', response); // Debug log
                        if (response.success) {
                            availableSlots = response.data.slots;
                            
                            if (bookingType === 'day-use') {
                                displayDayUseSlots(response.data);
                            } else {
                                displayOvernightSlots(response.data);
                            }
                            
                            $("#book-button").prop("disabled", false).text("Book Now");
                        } else {
                            showError("No availability found for selected dates");
                        }
                    },
                    error: function(xhr) {
                        console.log('Availability error:', xhr); // Debug log
                        const error = xhr.responseJSON?.error || "Error checking availability";
                        showError(error);
                    }
                });
            }

            function displayDayUseSlots(data) {
                const container = $("#available-slots-list");
                container.empty();

                if (data.slots.length === 0) {
                    container.html('<p class="text-danger">No available slots for selected date</p>');
                    return;
                }

                data.slots.forEach(slot => {
                    const slotHtml = `
                        <div class="form-check mb-2">
                            <input class="form-check-input slot-checkbox" type="checkbox" 
                                   value="${slot.id}" id="slot_${slot.id}" 
                                   data-price="${slot.price}" data-name="${slot.name}">
                            <label class="form-check-label" for="slot_${slot.id}">
                                <strong>${slot.name}</strong> (${slot.start_time} - ${slot.end_time}, ${slot.duration_hours} hrs) - $${slot.price}
                            </label>
                        </div>
                    `;
                    container.append(slotHtml);
                });

                $("#available-slots-container").show();
            }

            function displayOvernightSlots(data) {
                if (data.slots.length === 0) {
                    showError("No overnight availability for selected dates");
                    return;
                }

                const slot = data.slots[0]; // Overnight bookings use single slot
                const nights = calculateNights(data.start_date, data.end_date);
                const totalPrice = slot.total_price;

                $("#price-per-night").text(slot.price_per_night.toFixed(2));
                $("#overnight-total-price").text(totalPrice.toFixed(2));
                $("#overnight-price-summary").show();

                // Store the slot ID for booking
                selectedSlots = [slot.id];
            }

            function updateSelectedSlots() {
                selectedSlots = [];
                let totalPrice = 0;
                let selectedNames = [];

                $(".slot-checkbox:checked").each(function() {
                    const slotId = $(this).val();
                    const price = parseFloat($(this).data("price"));
                    const name = $(this).data("name");

                    selectedSlots.push(slotId);
                    totalPrice += price;
                    selectedNames.push(name);
                });

                if (selectedSlots.length > 0) {
                    $("#selected-slots-text").text(selectedNames.join(", "));
                    $("#total-price").text(totalPrice.toFixed(2));
                    $("#selected-slots-summary").show();
                } else {
                    $("#selected-slots-summary").hide();
                }
            }

            function submitBooking() {
                const bookingType = $("#booking_type").val();
                const startDate = convertDateFormat($("#check__in").val());
                let endDate = null;

                if (bookingType === 'day-use') {
                    endDate = startDate; // For day-use, end date is same as start date
                } else {
                    endDate = $("#check__out").val() ? convertDateFormat($("#check__out").val()) : null;
                }

                if (bookingType === 'day-use' && selectedSlots.length === 0) {
                    showError("Please select at least one time slot");
                    return;
                }

                if (bookingType === 'overnight' && !endDate) {
                    showError("Please select checkout date for overnight booking");
                    return;
                }

                $("#book-button").prop("disabled", true).text("Processing...");

                const bookingData = {
                    chalet_id: chaletId,
                    booking_type: bookingType,
                    start_date: startDate,
                    slot_ids: selectedSlots,
                    adults_count: 1, // Default values
                    children_count: 0
                };

                // Only add end_date if it's provided (for overnight bookings)
                if (endDate) {
                    bookingData.end_date = endDate;
                }

                submitBookingWithData(bookingData);
            }

            function submitBookingWithData(bookingData) {
                console.log('Submitting booking data:', bookingData); // Debug log

                $.ajax({
                    url: '/api/bookings',
                    method: 'POST',
                    data: bookingData,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess("Booking created successfully! Redirecting to confirmation page...");
                            // Redirect to booking confirmation page
                            setTimeout(() => {
                                window.location.href = response.data.confirmation_url;
                            }, 2000);
                        } else {
                            showError(response.error || "Error creating booking");
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 401) {
                            // User is not authenticated, store booking data and show login modal
                            pendingBookingData = bookingData;
                            $("#book-button").prop("disabled", false).text("Book Now");
                            showError("Please login to make a booking");
                            $('#loginModal').modal('show');
                        } else {
                            const error = xhr.responseJSON?.error || "Error creating booking";
                            showError(error);
                            $("#book-button").prop("disabled", false).text("Book Now");
                        }
                    }
                });
            }

            function convertDateFormat(dateStr) {
                // Convert dd-mm-yyyy to yyyy-mm-dd
                const parts = dateStr.split('-');
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }

            function calculateNights(startDate, endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                return Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            }

            // Store booking data for retry after login
            let pendingBookingData = null;

            function showError(message) {
                // Create a Bootstrap alert
                const alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                // Remove any existing alerts
                $('.alert').remove();
                
                // Add the alert at the top of the booking form
                $('#booking-form').prepend(alertHtml);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            }

            function showSuccess(message) {
                // Create a Bootstrap alert
                const alertHtml = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                // Remove any existing alerts
                $('.alert').remove();
                
                // Add the alert at the top of the booking form
                $('#booking-form').prepend(alertHtml);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            }

            // Handle successful login and retry booking
            $(document).on('hidden.bs.modal', '#loginModal', function () {
                // Check if user is now logged in and we have pending booking data
                if (pendingBookingData) {
                    // Check if user is authenticated by making a simple request
                    $.ajax({
                        url: '/api/user/check-auth',
                        method: 'GET',
                        success: function(response) {
                            if (response.authenticated) {
                                showSuccess("Login successful! Proceeding with your booking...");
                                // Retry the booking
                                submitBookingWithData(pendingBookingData);
                                pendingBookingData = null;
                            }
                        },
                        error: function() {
                            // User is still not authenticated
                            pendingBookingData = null;
                        }
                    });
                }
            });
        });
    </script>
@endsection
