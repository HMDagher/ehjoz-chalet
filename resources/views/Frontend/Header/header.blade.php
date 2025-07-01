<!-- header area -->
    <div class="header__top">
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-lg-6 col-md-6">
                    <div class="social__links">
                        <a class="link__item gap-10" href="tel:{{ optional($settings)->support_phone ?? '+12505550199' }}"><i class="flaticon-phone-flip"></i> {{ optional($settings)->support_phone ?? '+12505550199' }}</a>
                        <a class="link__item gap-10" href="mailto:{{ optional($settings)->support_email ?? 'info@example.com' }}"><i class="flaticon-envelope"></i> {{ optional($settings)->support_email ?? 'info@example.com' }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <header class="main__header header__function">
        <div class="container">
            <div class="row">
                <div class="main__header__wrapper">
                    <div class="main__nav">
                        @include('Frontend.Header.nav')
                    </div>
                    <div class="main__logo">
                        <a href="{{route('index')}}"><img class="logo__class" src="{{ asset(optional($settings)->site_logo ?? 'assets/images/logo/logo.png') }}" alt="{{ optional($settings)->site_name ?? 'Moonlit' }}" style="width: 170px; height: 40px; object-fit: contain;"></a>
                    </div>
                    <div class="main__right">
                        <a href="#" class="theme-btn btn-style sm-btn border d-none d-lg-block" aria-label="Login Button" data-bs-toggle="modal" data-bs-target="#loginModal"><span>Sign In</span></a>
                        <a href="#" class="theme-btn btn-style sm-btn border d-none d-lg-block" aria-label="Sign Up Button" data-bs-toggle="modal" data-bs-target="#signupModal"><span>Sign Up</span></a>
                        <button class="theme-btn btn-style sm-btn fill menu__btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                            <span><img src="{{asset('assets/images/icon/menu-icon.svg')}}" alt=""></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- header area end -->