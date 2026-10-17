{{-- Aset <head> bersama: ikon, font (hanya Inter), CSS & JS. Dipakai layout aplikasi dan halaman login. --}}
<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
