<footer class="rts__section rts__footer is__common__footer footer__background has__shape">
    <div class="section__shape">
        <div class="shape__1">
            <img src="{{asset('assets/images/footer/shape-1.svg')}}" alt="">
        </div>
        <div class="shape__2">
            <img src="{{asset('assets/images/footer/shape-2.svg')}}" alt="">
        </div>
        <div class="shape__3">
            <img src="{{asset('assets/images/footer/shape-3.svg')}}" alt="">
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="footer__newsletter">
                <span class="h2">Join Our Newsletter</span>
                <div class="rts__form">
                    <form action="#" method="post">
                        <input type="email" name="email" id="subscription" placeholder="Enter your mail" required>
                        <button type="submit" >Subscribe</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="footer__widget__wrapper">
                <div class="rts__widget">
                    <a href="{{route('index')}}"><img class="footer__logo" src="{{ asset(optional($settings)->site_logo ?? 'assets/images/logo/logo.png') }}" alt="{{ optional($settings)->site_name ?? 'Moonlit' }} logo" style="width: 170px; height: 40px; object-fit: contain;"></a>
                    <p class="font-sm max-290 mt-20">
                        {{ optional($settings)->site_description ?? "Each room features plush bedding, high-quality linens, and a selection of ensure a restful night's sleep." }}
                    </p>
                </div>
                <div class="rts__widget">
                    <span class="widget__title">Quick Links</span>
                    <ul>
                        <li><a href="{{route('index')}}" aria-label="footer__link">Home</a></li>
                        <li><a href="{{route('chalets')}}" aria-label="footer__link">Chalets</a></li>
                        <li><a href="{{route('contact')}}" aria-label="footer__link">Contact</a></li>
                    </ul>
                </div>
                <div class="rts__widget">
                    <span class="widget__title">Contact Us</span>
                    <ul>
                        <li><a aria-label="footer__contact" href="tel:{{ optional($settings)->support_phone ?? '+12505550199' }}"><i class="flaticon-phone-flip"></i> {{ optional($settings)->support_phone ?? '+12505550199' }}</a></li>
                        <li><a aria-label="footer__contact" href="mailto:{{ optional($settings)->support_email ?? 'Moonlit@gmail.com' }}"><i class="flaticon-envelope"></i>{{ optional($settings)->support_email ?? 'Moonlit@gmail.com' }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="copyright__text">
        <div class="container">
            <div class="row">
                <div class="copyright__wrapper">
                    <div class="footer__social__link">
                        @php
                            $socials = json_decode(optional($settings)->social_network ?? '[]', true);
                        @endphp
                        @if(is_array($socials))
                            @foreach($socials as $social)
                                <a href="{{ $social['url'] ?? '#' }}" aria-label="footer__social" class="link__item">{{ $social['name'] ?? '' }}</a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
 </footer>