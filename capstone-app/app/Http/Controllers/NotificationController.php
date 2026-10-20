<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F48 — notifikasi dalam aplikasi; daftarnya tampil di popover lonceng (components/notification-menu). */
class NotificationController extends Controller
{
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Hanya izinkan tautan internal aplikasi.
        return $url && str_starts_with($url, url('/')) ? redirect()->to($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
