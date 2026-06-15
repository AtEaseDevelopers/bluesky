@extends('member.layouts.header')

@section('title', 'Admin Dashboard')

@include('member.layouts.nav')  <!-- Include the navigation -->

<main class="py-4">
    @yield('content')
</main>

@include('member.layouts.footer')  <!-- Include the footer -->
