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
                        <!-- single input -->
                        <div class="query__input">
                            <label for="check__in" class="query__label">Check In</label>
                            <input type="text" id="check__in" name="checkin" value="{{ $checkin ?? request()->checkin }}" placeholder="{{ now()->format('d M Y') }}" required>
                            <div class="query__input__icon">
                                <i class="flaticon-calendar"></i>
                            </div>
                        </div>
                        <!-- single input end -->
    
                         <!-- single input -->
                        <div class="query__input">
                            <label for="check__out" class="query__label">Check Out</label>
                            <input type="text" id="check__out" name="checkout" value="{{ $checkout ?? request()->checkout }}" placeholder="{{ now()->addDay()->format('d M Y') }}" required>
                            <div class="query__input__icon">
                                <i class="flaticon-calendar"></i>
                            </div>
                        </div>
                        <!-- single input end -->
        
                        <!-- single input -->
                        <div class="query__input">
                            <label for="adult" class="query__label">Adult</label>
                            <div class="query__input__position">
                                <select name="adult" id="adult" class="form-select">
                                    <option value="1">1 Person</option>
                                    <option value="2">2 Person</option>
                                    <option value="3">3 Person</option>
                                    <option value="4">4 Person</option>
                                    <option value="5">5 Person</option>
                                    <option value="6">6 Person</option>
                                    <option value="7">7 Person</option>
                                </select>
                                <div class="query__input__icon">
                                    <i class="flaticon-user"></i>
                                </div>
                            </div>
                        </div>
                        <!-- single input end -->
        
                        <!-- single input -->
                        <div class="query__input">
                            <label for="child" class="query__label">Child</label>
                            <div class="query__input__position">
                                <select name="child" id="child" class="form-select">
                                    <option value="1">1 Child</option>
                                    <option value="2">2 Child</option>
                                    <option value="3">3 Child</option>
                                    <option value="4">4 Child</option>
                                    <option value="5">5 Child</option>
                                    <option value="6">6 Child</option>
                                    <option value="7">7 Child</option>
                                </select>
                                <div class="query__input__icon">
                                    <i class="flaticon-user"></i>
                                </div>
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