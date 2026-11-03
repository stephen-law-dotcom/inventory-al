<?php

namespace App\View\Composers;

use Illuminate\View\View;

class InventoryLayoutComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();
        $currentUser = auth()->user()->loadMissing('role');
        $activeMenu = $data['activeMenu'] ?? 'dashboard';
        $activeCompanyId = $data['activeCompanyId'] ?? (request('company_id') ? (int) request('company_id') : null);
        $companyQuery = $activeCompanyId ? ['company_id' => $activeCompanyId] : [];
        $canSeePrices = $data['canSeePrices'] ?? $currentUser->canSeePrices();
        $activeReport = $data['activeReport'] ?? request('report', 'requests');

        $nav = $this->navigation($currentUser, $companyQuery);
        $masterNav = $this->masterNavigation($currentUser);
        $reportNav = $this->reportNavigation($currentUser);
        $workflowSubnav = $this->workflowNavigation($currentUser, $companyQuery);
        $workflowTab = in_array(request('tab'), ['approval', 'history'], true) ? request('tab') : 'active';
        $requestedDispatchTab = request('tab') === 'outbound' ? 'draft' : request('tab');
        $dispatchTab = in_array($requestedDispatchTab, ['draft', 'transit', 'receiving', 'history'], true)
            ? $requestedDispatchTab
            : ($currentUser->hasPermission('dispatches.manage')
                ? 'draft'
                : ($currentUser->hasPermission('site_receipts.manage') ? 'receiving' : 'transit'));
        $titles = [
            'dashboard' => 'Dashboard',
            'master' => 'Master Data',
            'requests' => 'Order Request',
            'approvals' => 'Approval Saya',
            'vendor-prices' => 'Harga Vendor',
            'purchase-orders' => 'Purchase Order',
            'receipts' => 'Penerimaan Gudang',
            'credit-notes' => 'Credit Note',
            'dispatches' => 'Pengiriman Barang',
            'usage' => 'Pemakaian Unit',
            'stock' => 'Stok Barang',
            'stock-opname' => 'Stock Opname',
            'reports' => 'Report',
            'maintenance' => 'Pemeliharaan Sistem',
            'notifications' => 'Notifikasi',
        ];

        $view->with([
            'currentUser' => $currentUser,
            'canSeePrices' => $canSeePrices,
            'canManageVendorPrices' => $data['canManageVendorPrices'] ?? $currentUser->canManageVendorPrices(),
            'isAdmin' => $data['isAdmin'] ?? $currentUser->isAdmin(),
            'canManageMasterData' => $currentUser->hasPermission('master.manage'),
            'roleName' => $currentUser->roleName(),
            'activeCompanyId' => $activeCompanyId,
            'companyQuery' => $companyQuery,
            'companyTabCounts' => $data['companyTabCounts'] ?? ['all' => 0],
            'companyScopedMenus' => ['dashboard', 'requests', 'approvals', 'vendor-prices', 'purchase-orders', 'receipts', 'credit-notes', 'dispatches', 'usage'],
            'companyTabUrl' => $this->companyTabUrl(...),
            'nav' => $nav,
            'masterNav' => $masterNav,
            'activeReport' => $activeReport,
            'reportNav' => $reportNav,
            'reportFilterQuery' => request()->except('report', 'page', 'status'),
            'workflowTab' => $workflowTab,
            'dispatchTab' => $dispatchTab,
            'workflowSubnav' => $workflowSubnav,
            'activeMaster' => $data['activeMaster'] ?? 'camps',
            'pageTitle' => $titles[$activeMenu] ?? $titles['dashboard'],
            'purchaseOrderStatusLabel' => $this->purchaseOrderStatusLabel(...),
            'creditNoteStatusLabel' => $this->creditNoteStatusLabel(...),
            'workflowStatusLabel' => $this->workflowStatusLabel(...),
        ]);
    }

    private function navigation($user, array $companyQuery): array
    {
        $nav = [];
        $definitions = [
            ['dashboard.view', 'dashboard', 'dashboard', route('dashboard', $companyQuery), 'Dashboard'],
            ['master.view', 'master', 'master', route('master.camps'), 'Master Data'],
            ['requests.view', 'requests', 'request', route('requests', $companyQuery), 'Order Request'],
            ['approvals.view', 'approvals', 'shield', route('approvals', $companyQuery), 'Approval Saya'],
            ['purchase_orders.view', 'purchase-orders', 'cart', route('purchase-orders', $companyQuery), 'Purchase Order'],
            ['receipts.view', 'receipts', 'inbox', route('receipts', $companyQuery), 'Penerimaan'],
            ['credit_notes.view', 'credit-notes', 'return', route('credit-notes', $companyQuery), 'Credit Note'],
            ['usage.view', 'usage', 'tool', route('usage', $companyQuery), 'Pemakaian'],
            ['stock.view', 'stock', 'box', route('stock'), 'Stok'],
            ['stock_opname.view', 'stock-opname', 'checklist', route('stock-opname'), 'Stock Opname'],
            ['reports.view', 'reports', 'chart', route('reports'), 'Report'],
        ];

        foreach ($definitions as [$permission, $key, $icon, $url, $label]) {
            if ($user->hasPermission($permission)) {
                $nav[] = [$key, $icon, $url, $label];
            }
        }
        if ($user->hasPermission('vendor_prices.view') || $user->canApproveEquipment() || $user->hasPermission('approvals.director')) {
            $position = array_search('purchase-orders', array_column($nav, 0), true);
            array_splice($nav, $position === false ? count($nav) : $position, 0, [[
                'vendor-prices', 'tag', route('vendor-prices', $companyQuery), 'Harga Vendor',
            ]]);
        }
        if ($user->hasPermission('dispatches.view')) {
            $defaultTab = $user->hasPermission('dispatches.manage')
                ? 'draft'
                : ($user->hasPermission('site_receipts.manage') ? 'receiving' : 'transit');
            $position = array_search('usage', array_column($nav, 0), true);
            array_splice($nav, $position === false ? count($nav) : $position, 0, [[
                'dispatches', 'truck', route('dispatches', array_merge($companyQuery, ['tab' => $defaultTab])), 'Pengiriman Barang',
            ]]);
        }
        if ($user->isAdmin()) {
            $nav[] = ['maintenance', 'database', route('system-maintenance'), 'Pemeliharaan Sistem'];
        }

        return $nav;
    }

    private function masterNavigation($user): array
    {
        $nav = [
            ['companies', route('master.companies'), 'Perusahaan'],
            ['camps', route('master.camps'), 'Camp'],
            ['vendors', route('master.vendors'), 'Vendor'],
            ['units', route('master.units'), 'Unit'],
            ['item-categories', route('master.item-categories'), 'Kategori'],
            ['items', route('master.items'), 'Barang'],
            ['uoms', route('master.uoms'), 'Satuan'],
        ];
        if ($user->hasPermission('users.manage')) {
            $nav[] = ['users', route('master.users'), 'User'];
        }
        if ($user->hasPermission('roles.manage')) {
            $nav[] = ['roles', route('master.roles'), 'Role'];
        }

        return $nav;
    }

    private function reportNavigation($user): array
    {
        $nav = [
            'procurement_flow' => ['Proses Pengadaan', route('reports.show', 'procurement-flow'), 'Pengadaan'],
            'requests' => ['Order Request', route('reports'), 'Pengadaan'],
            'vendors' => ['Vendor', route('reports.show', 'vendors'), 'Pengadaan'],
            'purchase_orders' => ['Purchase Order', route('reports.show', 'purchase-orders'), 'Pengadaan'],
            'outstanding_po' => ['Outstanding PO', route('reports.show', 'outstanding-po'), 'Pengadaan'],
            'receipts' => ['Penerimaan', route('reports.show', 'receipts'), 'Pengadaan'],
            'credit_notes' => ['Credit Note', route('reports.show', 'credit-notes'), 'Pengadaan'],
            'stock' => ['Stok Kritis', route('reports.show', 'stock'), 'Persediaan'],
            'stock_locations' => ['Stok per Lokasi', route('reports.show', 'stock-locations'), 'Persediaan'],
            'opnames' => ['Stock Opname', route('reports.show', 'opnames'), 'Persediaan'],
            'stock_movements' => ['Kartu Stok', route('reports.show', 'stock-movements'), 'Persediaan'],
            'inventory_planning' => ['EOQ / ROP', route('reports.show', 'inventory-planning'), 'Persediaan'],
            'usage' => ['Pemakaian', route('reports.show', 'usage'), 'Operasional Site'],
            'dispatches' => ['Pengiriman Site', route('reports.show', 'dispatches'), 'Operasional Site'],
            'activity_logs' => ['Log User', route('reports.show', 'activity-logs'), 'Audit'],
        ];
        if (! $user->hasPermission('reports.stock_movements')) {
            unset($nav['stock_movements']);
        }
        if (! $user->hasPermission('reports.activity_logs')) {
            unset($nav['activity_logs']);
        }

        return $nav;
    }

    private function workflowNavigation($user, array $companyQuery): array
    {
        return [
            'requests' => [
                ['active', route('requests', $companyQuery), 'Order Request'],
                ['approval', route('requests', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Order Request'],
                ['history', route('requests', array_merge($companyQuery, ['tab' => 'history'])), 'History Order Request'],
            ],
            'vendor-prices' => [
                ['active', route('vendor-prices', $companyQuery), 'Harga Vendor'],
                ['approval', route('vendor-prices', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Pengadaan'],
                ['history', route('vendor-prices', array_merge($companyQuery, ['tab' => 'history'])), 'History Harga Vendor'],
            ],
            'purchase-orders' => [
                ['active', route('purchase-orders', $companyQuery), 'Purchase Order'],
                ['approval', route('purchase-orders', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Purchase Order'],
                ['history', route('purchase-orders', array_merge($companyQuery, ['tab' => 'history'])), 'History Purchase Order'],
            ],
            'receipts' => [
                ['active', route('receipts', $companyQuery), 'Penerimaan'],
                ['history', route('receipts', array_merge($companyQuery, ['tab' => 'history'])), 'History Penerimaan'],
            ],
            'credit-notes' => [
                ['active', route('credit-notes', $companyQuery), 'Credit Note'],
                ['history', route('credit-notes', array_merge($companyQuery, ['tab' => 'history'])), 'History Credit Note'],
            ],
            'usage' => [
                ['active', route('usage', $companyQuery), 'Pemakaian Bulan Ini'],
                ['history', route('usage', array_merge($companyQuery, ['tab' => 'history'])), 'History Pemakaian'],
            ],
            'dispatches' => array_values(array_filter([
                $user->hasPermission('dispatches.manage') ? ['draft', route('dispatches', array_merge($companyQuery, ['tab' => 'draft'])), 'Packing List'] : null,
                ($user->hasPermission('dispatches.manage') || ! $user->hasPermission('site_receipts.manage')) ? ['transit', route('dispatches', array_merge($companyQuery, ['tab' => 'transit'])), 'Dalam Perjalanan'] : null,
                $user->hasPermission('site_receipts.manage') ? ['receiving', route('dispatches', array_merge($companyQuery, ['tab' => 'receiving'])), 'Penerimaan Site'] : null,
                ['history', route('dispatches', array_merge($companyQuery, ['tab' => 'history'])), 'Riwayat'],
            ])),
        ];
    }

    private function companyTabUrl(?int $companyId = null): string
    {
        $params = request()->except('company_id', 'page');
        if ($companyId) {
            $params['company_id'] = $companyId;
        }

        return url()->current().($params ? '?'.http_build_query($params) : '');
    }

    private function purchaseOrderStatusLabel($status): string
    {
        return [
            'draft' => 'Draft', 'on_progress' => 'On Progress', 'submitted' => 'Submitted',
            'issued' => 'Submitted', 'partial_received' => 'Partial Received', 'received' => 'Received',
            'rejected' => 'Ditolak',
        ][$status] ?? str_replace('_', ' ', (string) $status);
    }

    private function creditNoteStatusLabel($status): string
    {
        return [
            'draft' => 'Draft', 'sent_to_vendor' => 'Dikirim ke Vendor',
            'credited' => 'Credited / Dana Kembali', 'closed' => 'Selesai',
        ][$status] ?? str_replace('_', ' ', (string) $status);
    }

    private function workflowStatusLabel($status): string
    {
        return [
            'draft' => 'Draft', 'submitted' => 'Menunggu Logistik',
            'logistic_checked' => 'Menunggu Chief Mechanic', 'waiting_manager_camp' => 'Menunggu Manager Camp',
            'ready_for_quotation' => 'Siap Input Harga', 'vendor_selected' => 'Vendor Dipilih',
            'ready_for_po' => 'Siap Dibuat PO', 'po_created' => 'PO Dibuat', 'po_issued' => 'PO Submitted',
            'partial_received' => 'Diterima Sebagian', 'goods_received' => 'Selesai Diterima',
            'waiting_director' => 'Menunggu Direktur', 'rejected' => 'Ditolak',
            'pending' => 'Menunggu', 'waiting' => 'Menunggu', 'approved' => 'Disetujui',
            'not_required' => 'Tidak Perlu', 'pending_equipment' => 'Menunggu Peralatan',
            'pending_director' => 'Menunggu Direktur',
        ][$status] ?? ucwords(str_replace('_', ' ', (string) $status));
    }
}
