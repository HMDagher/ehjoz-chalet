<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="description" content="{{ optional($settings)->site_description ?? 'Ehjoz Chalets - Lebanon Chalets Booking Platform' }}">
<meta name="keywords" content="{{ optional($settings)->seo_keywords ?? 'hotel, resort, Spa' }}">
<meta name="robots" content="index, follow">
<!-- for open graph social media -->
<meta property="og:title" content="{{ optional($settings)->seo_title ?? 'Ehjoz Chalets' }}">
<meta property="og:description" content="{{ optional($settings)->site_description ?? 'Ehjoz Chalets - Lebanon Chalets Booking Platform' }}">
<!-- for twitter sharing -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ optional($settings)->seo_title ?? 'Ehjoz Chalets' }}">
<meta name="twitter:description" content="{{ optional($settings)->site_description ?? 'Ehjoz Chalets - Lebanon Chalets Booking Platform' }}">
<!-- favicon -->
<link rel="icon" href="{{ asset(optional($settings)->site_favicon ?? 'assets/images/logo/favicon.ico') }}" type="image/x-icon">
<!-- title -->
<title>{{ optional($settings)->site_name ?? 'Ehjoz Chalets' }} - @yield('page_title')</title>
