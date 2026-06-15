@extends('admin.layouts.header')

@section('title', 'Admin Dashboard')

@include('admin.includes.nav')  <!-- Include the navigation -->

<main class="py-4">
    @yield('content')
</main>

@include('admin.layouts.footer')  <!-- Include the footer -->
