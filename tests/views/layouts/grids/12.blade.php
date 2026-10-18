{{-- Test stub of the SeAT layout: renders plugin sections without the core chrome. --}}
<title>@yield('title')</title>
<h1>@yield('page_header')</h1>
@include('web::includes.notifications')
@yield('full')
@stack('head')
@stack('javascript')
