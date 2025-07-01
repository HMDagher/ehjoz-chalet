    <!-- page header -->
    <div class="rts__section page__hero__height page__hero__bg if__has__search" style="background-image: url({{asset('assets/images/pages/header__bg.webp')}});">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-12">
                    <div class="page__hero__content">
                        <h1 class="wow fadeInUp">{{$title ?? ''}}</h1>
                        <p class="wow fadeInUp font-sm">{{$desc ?? ''}}</p>
                    </div>
                </div>
            </div>
            <div class="row mt-60 text-start">
                <form action="{{ route('chalets') }}" method="get" class="advance__search">
                    <div class="advance__search__wrapper wow fadeInUp">
                        <!-- booking type input -->
                        <div class="query__input">
                            <label for="booking_type" class="query__label">Booking Type</label>
                            <div class="query__input__position">
                                <select name="booking_type" id="booking_type" class="form-select">
                                    <option value="day-use" {{ ($bookingType ?? '') == 'day-use' ? 'selected' : '' }}>Day Use</option>
                                    <option value="overnight" {{ ($bookingType ?? '') == 'overnight' ? 'selected' : '' }}>Overnight</option>
                                </select>
                                <div class="query__input__icon">
                                    <i class="flaticon-calendar"></i>
                                </div>
                            </div>
                        </div>
                        <!-- booking type input end -->

                        <!-- single input -->
                        <div class="query__input">
                            <label for="check__in" class="query__label">Check In</label>
                            <input type="text" id="check__in" name="check__in" value="{{ $checkin ?? request()->check__in }}" placeholder="{{ now()->format('d M Y') }}" required>
                            <div class="query__input__icon">
                                <i class="flaticon-calendar"></i>
                            </div>
                        </div>
                        <!-- single input end -->
    
                        <!-- single input -->
                        <div class="query__input checkout-field">
                            <label for="check__out" class="query__label">Check Out</label>
                            <input type="text" id="check__out" name="check__out" value="{{ $checkout ?? request()->check__out }}" placeholder="{{ now()->addDay()->format('d M Y') }}">
                            <div class="query__input__icon">
                                <i class="flaticon-calendar"></i>
                            </div>
                        </div>
                        <!-- single input end -->
    
                        <!-- submit button -->
                        <button type="submit" class="theme-btn btn-style fill no-border search__btn">
                            <span><i class="flaticon-search-1"></i> Search</span>
                        </button>
                        <!-- submit button end -->
                    </div>
                </form>
            </div>
        </div>
     </div>
    <!-- page header end -->