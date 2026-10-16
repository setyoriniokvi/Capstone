<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F48 — notifikasi dalam aplikasi. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('shared.notifications', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Hanya izinkan tautan internal aplikasi.
        return $url && str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
