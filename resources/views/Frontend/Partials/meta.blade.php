<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="description" content="{{ optional($settings)->site_description ?? 'Moonlit - Hotel and Resort HTML Template' }}">
<meta name="keywords" content="{{ optional($settings)->seo_keywords ?? 'hotel, resort, Spa' }}">
<meta name="robots" content="index, follow">
<!-- for open graph social media -->
<meta property="og:title" content="{{ optional($settings)->seo_title ?? 'Hotel and Resort' }}">
<meta property="og:description" content="{{ optional($settings)->site_description ?? 'Moonlit - Hotel and Resort HTML Template' }}">
<!-- for twitter sharing -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ optional($settings)->seo_title ?? 'Hotel and Resort' }}">
<meta name="twitter:description" content="{{ optional($settings)->site_description ?? 'Moonlit - Hotel and Resort HTML Template' }}">
<!-- favicon -->
<link rel="icon" href="{{ asset(optional($settings)->site_favicon ?? 'assets/images/logo/favicon.png') }}" type="image/x-icon">
<!-- title -->
<title>{{ optional($settings)->site_name ?? 'Moonlit' }} - @yield('page_title')</title>
