<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Services\InventoryPageContextService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly InventoryPageContextService $pages) {}

    public function index(Request $request)
    {
        $status = $request->input('status') === 'unread' ? 'unread' : 'all';
        $query = auth()->user()->systemNotifications()->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        }

        return view('inventory.notifications.index', array_merge($this->pages->base('notifications'), [
            'notificationPaginator' => $query->paginate(25)->withQueryString(),
            'notificationStatus' => $status,
        ]));
    }

    public function readNotification(SystemNotification $systemNotification)
    {
        abort_unless($systemNotification->user_id === auth()->id(), 403);

        $systemNotification->update(['read_at' => now()]);

        return redirect($systemNotification->url ?: route('dashboard'));
    }

    public function markAllNotificationsRead()
    {
        auth()->user()->systemNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    }
}
