{{-- Aset <head> bersama: ikon browser, font (hanya Inter), CSS & JS. Dipakai layout aplikasi dan halaman login.
     Ikon browser tetap di public/icons karena diminta browser lewat URL tetap; gambar aplikasi lain ada di resources/images. --}}
<link rel="icon" type="image/png" href="{{ asset('icons/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
