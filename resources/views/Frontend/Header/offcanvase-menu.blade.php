    <!-- offcanvase menu -->
    <div class="offcanvas offcanvas-start" id="offcanvasRight">
        <div class="rts__btstrp__offcanvase">
            <div class="offcanvase__wrapper">
                <div class="left__side mobile__menu">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    <div class="offcanvase__top">
                        <div class="offcanvase__logo">
                            <a href="{{route('index')}}">
                                <img src="{{ asset(optional($settings)->site_logo ?? 'assets/images/logo/logo.png') }}" alt="logo" style="width: 170px; height: 40px; object-fit: contain;">
                            </a>
                        </div>
                        <p class="description">
                            {{ optional($settings)->site_description ?? 'Welcome to Moonlit, where luxury meets comfort in the heart of canada. Since 1999, we have been dedicated to providing.' }}
                        </p>
                    </div>
                    <div class="offcanvase__mobile__menu">
                        <div class="mobile__menu__active"></div>
                    </div>
                    <div class="offcanvase__bottom">
                        <div class="offcanvase__address">
                            <div class="item">
                                <span class="h6">Phone</span>
                                <a href="tel:{{ optional($settings)->support_phone ?? '+1234567890' }}"><i class="flaticon-phone-flip"></i> {{ optional($settings)->support_phone ?? '+1234567890' }}</a>
                            </div>
                            <div class="item">
                                <span class="h6">Email</span>
                                <a href="mailto:{{ optional($settings)->support_email ?? 'info@hostie.com' }}"><i class="flaticon-envelope"></i>{{ optional($settings)->support_email ?? 'info@hostie.com' }}</a>
                            </div>
                        </div>
                        <div class="offcanvase__auth mt-4">
                            @auth
                                @php
                                    $dashboardUrl = '/';
                                    if(auth()->user()->hasRole('admin')) {
                                        $dashboardUrl = '/admin';
                                    } elseif(auth()->user()->hasRole('owner')) {
                                        $dashboardUrl = '/chalet';
                                    } elseif(auth()->user()->hasRole('customer')) {
                                        $dashboardUrl = '/customer';
                                    }
                                @endphp
                                <a href="{{ $dashboardUrl }}" class="theme-btn btn-style sm-btn border w-100 mb-2">Dashboard</a>
                            @else
                                <a href="#" class="theme-btn btn-style sm-btn border w-100 mb-2" data-bs-toggle="modal" data-bs-target="#loginModal">Sign In</a>
                                <a href="#" class="theme-btn btn-style sm-btn border w-100" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</a>
                            @endauth
                        </div>
                    </div>
                </div>
                <div class="right__side desktop__menu">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    <div class="rts__desktop__menu">
                       <nav class="desktop__menu offcanvas__menu">
                            <ul class="list-unstyled">
                                <li class="slide">
                                    <a class="slide__menu__item" href="{{route('index')}}">Home
                                    </a>
                                </li>
                                <li class="slide">
                                    <a class="slide__menu__item" href="{{route('chalets')}}">Chalets
                                    </a>
                                </li>
                                <li class="slide">
                                    <a class="slide__menu__item" href="{{route('contact')}}">Contact Us
                                    </a>
                                </li>
                            </ul>
                       </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- offcanvase menu end -->