    <!-- footer style two -->
    <div class="rts__section rts__footer is__footer__two footer__background has__background__image">
        <div class="container">
            <div class="row">
                <div class="footer__widget__wrapper">
                    <div class="rts__widget">
                        <a href="{{route('index')}}"><img src="{{ asset(optional($settings)->site_logo ?? 'assets/images/logo/logo.png') }}" alt="{{ optional($settings)->site_name ?? 'Moonlit' }} logo" style="width: 170px; height: 40px; object-fit: contain;"></a>
                        <p class="font-sm max-290 mt-20 text-white">
                            {{ optional($settings)->site_description ?? "Each room features plush bedding, high-quality linens, and a selection of ensure a restful night's sleep." }}
                        </p>
                    </div>
                    <div class="rts__widget">
                        <span class="widget__title">Quick Links</span>
                        <ul>
                            <li><a href="{{route('index')}}">Home</a></li>
                            <li><a href="{{route('chalets')}}">Chalets</a></li>
                            <li><a href="{{route('contact')}}">Contact</a></li>
                        </ul>
                    </div>
                    <div class="rts__widget">
                        <span class="widget__title">Contact Us</span>
                        <ul>
                            <li><a href="tel:{{ optional($settings)->support_phone ?? '+12505550199' }}"><i class="flaticon-phone-flip"></i> {{ optional($settings)->support_phone ?? '+12505550199' }}</a></li>
                            <li><a href="mailto:{{ optional($settings)->support_email ?? 'Moonlit@gmail.com' }}"><i class="flaticon-envelope"></i>{{ optional($settings)->support_email ?? 'Moonlit@gmail.com' }}</a></li>
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
                                    <a href="{{ $social['url'] ?? '#' }}" class="link__item">{{ $social['name'] ?? '' }}</a>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- footer style two end -->