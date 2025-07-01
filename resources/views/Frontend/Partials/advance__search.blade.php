<div class="rts__section advance__search__section {{$class}}" {{$attr}}>
    <div class="container">
        <div class="row">
            <form action="{{route('chalets')}}" method="get" class="advance__search">
                <div class="advance__search__wrapper wow fadeInUp">
                    <!-- booking type input -->
                    <div class="query__input">
                        <label for="booking_type" class="query__label">Booking Type</label>
                        <select name="booking_type" id="booking_type" class="form-select">
                            <option value="day-use">Day Use</option>
                            <option value="overnight">Overnight</option>
                        </select>
                        <div class="query__input__icon">
                            <i class="flaticon-calendar"></i>
                        </div>
                    </div>
                    <!-- booking type input end -->

                    <!-- single input -->
                    <div class="query__input">
                        <label for="check__in" class="query__label">Check In</label>
                        <input type="text" id="check__in" name="check__in" placeholder="{{ now()->format('d M Y') }}" value="{{ $checkin ?? request('check__in') }}" required>
                        <div class="query__input__icon">
                            <i class="flaticon-calendar"></i>
                        </div>
                    </div>
                    <!-- single input end -->

                     <!-- single input -->
                    <div class="query__input checkout-field">
                        <label for="check__out" class="query__label">Check Out</label>
                        <input type="text" id="check__out" name="check__out" placeholder="{{ now()->addDay()->format('d M Y') }}" value="{{ $checkout ?? request('check__out') }}">
                        <div class="query__input__icon">
                            <i class="flaticon-calendar"></i>
                        </div>
                    </div>
                    <!-- single input end -->

                    <!-- submit button -->
                    <button class="theme-btn btn-style fill no-border search__btn">
                        <span>Check Now</span>
                    </button>
                    <!-- submit button end -->
                </div>
            </form>
        </div>
    </div>
</div>
