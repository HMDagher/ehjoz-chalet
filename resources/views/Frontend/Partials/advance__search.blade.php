<div class="rts__section advance__search__section {{$class}}" {{$attr}}>
    <div class="container">
        <div class="row">
            <form action="{{route('chalets')}}" method="get" class="advance__search">
                <div class="advance__search__wrapper wow fadeInUp">
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
                    <div class="query__input">
                        <label for="check__out" class="query__label">Check Out</label>
                        <input type="text" id="check__out" name="check__out" placeholder="{{ now()->addDay()->format('d M Y') }}" value="{{ $checkout ?? request('check__out') }}" required>
                        <div class="query__input__icon">
                            <i class="flaticon-calendar"></i>
                        </div>
                    </div>
                    <!-- single input end -->
    
                    <!-- single input -->
                    <div class="query__input">
                        <label for="adult" class="query__label ">Adult</label>
                        <select name="adult" id="adult" class="form-select">
                            <option value="1">1 Person</option>
                            <option value="2">2 Person</option>
                            <option value="3">3 Person</option>
                            <option value="4">4 Person</option>
                            <option value="5">5 Person</option>
                            <option value="6">6 Person</option>
                            <option value="7">7 Person</option>
                            <option value="8">8 Person</option>
                            <option value="9">9 Person</option>
                        </select>
                        <div class="query__input__icon">
                            <i class="flaticon-user"></i>
                        </div>
                    </div>
                    <!-- single input end -->
    
                    <!-- single input -->
                    <div class="query__input">
                        <label for="child" class="query__label ">Child</label>
                        <select name="child" id="child" class="form-select">
                            <option value="1">1 Child</option>
                            <option value="2">2 Child</option>
                            <option value="3">3 Child</option>
                            <option value="4">4 Child</option>
                            <option value="5">5 Child</option>
                            <option value="6">6 Child</option>
                            <option value="7">7 Child</option>
                            <option value="8">8 Child</option>
                            <option value="9">9 Child</option>
                        </select>
                        <div class="query__input__icon">
                            <i class="flaticon-user"></i>
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
