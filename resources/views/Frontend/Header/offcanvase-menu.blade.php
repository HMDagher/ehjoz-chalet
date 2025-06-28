    <!-- offcanvase menu -->
    <div class="offcanvas offcanvas-start" id="offcanvasRight">
        <div class="rts__btstrp__offcanvase">
            <div class="offcanvase__wrapper">
                <div class="left__side mobile__menu">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    <div class="offcanvase__top">
                        <div class="offcanvase__logo">
                            <a href="{{route('index')}}">
                                <img src="{{ asset(optional($settings)->site_logo_2 ?? 'assets/images/logo/logo__two.svg') }}" alt="logo">
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
                            <div class="item">
                                <span class="h6">Address</span>
                                <a href="#"><i class="flaticon-marker"></i> {{ optional($settings)->address ?? '280 Augusta Avenue, M5T 2L9 Toronto, Canada' }}</a>
                            </div>

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