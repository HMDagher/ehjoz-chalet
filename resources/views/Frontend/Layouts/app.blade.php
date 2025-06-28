<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js" data-theme="{{ $theme ?? 'light' }}">
<head>
     @include('Frontend.Partials.meta')
     @include('Frontend.Partials.style')
     @yield('css')
</head>
     <body class="{{ $body_class ?? '' }}">
          @include('Frontend.Components.preloader')
          @include('Frontend.Header.offcanvase-menu')


          {{-- Content Block --}}
          @yield('content')

          @include('Frontend.Partials.login-popup') 
          @include('Frontend.Partials.script')
          @yield('script')
     </body>
</html>
