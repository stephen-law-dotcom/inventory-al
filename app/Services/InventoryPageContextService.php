<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Company;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;

class InventoryPageContextService
{
    public function scope(): array
    {
        $user = auth()->user()->loadMissing('role');
        $companyIds = $user->scopedCompanyIds();
        $campIds = $user->scopedCampIds();
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $requestedCompanyId = request()->integer('company_id');
        $activeCompanyId = $requestedCompanyId && $companies->contains('id', $requestedCompanyId)
            ? $requestedCompanyId
            : null;
        $camps = Camp::with('company')
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->orderBy('name')
            ->get();

        return compact('user', 'companyIds', 'campIds', 'companies', 'activeCompanyId', 'camps') + [
            'visibleCampIds' => $camps->pluck('id')->all(),
            'restrict' => ! $user->isAdmin() || $activeCompanyId,
        ];
    }

    public function base(string $activeMenu, ?array $scope = null): array
    {
        $scope ??= $this->scope();
        $user = $scope['user'];

        return [
            'activeMenu' => $activeMenu,
            'activeMaster' => null,
            'companies' => $scope['companies'],
            'activeCompanyId' => $scope['activeCompanyId'],
            'companyTabCounts' => [],
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
            'isAdmin' => $user->isAdmin(),
        ];
    }

    public function stockOpnameWorklist(array $scope): array
    {
        $user = $scope['user'];
        $campIds = $scope['campIds'];
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];

        $pending = $user->canReviewStockOpname()
            ? StockOpnameItem::with(['stockOpname.camp', 'inventoryItem'])
                ->where('tolerance_review_status', 'pending')
                ->whereHas('stockOpname', fn ($query) => $query
                    ->where('status', StockOpname::STATUS_PENDING_REVIEW)
                    ->when(! $user->isAdmin() && $campIds, fn ($inner) => $inner->whereIn('camp_id', $visibleCampIds ?: [-1]))
                    ->when($restrict && ! $campIds, fn ($inner) => $inner->where(function ($access) use ($visibleCampIds) {
                        $access->whereNull('camp_id');
                        if ($visibleCampIds) {
                            $access->orWhereIn('camp_id', $visibleCampIds);
                        }
                    })))
                ->latest()
                ->get()
            : collect();

        $ready = $user->canPostStockOpname()
            ? StockOpname::with(['camp', 'items.inventoryItem', 'officers'])
                ->where('status', StockOpname::STATUS_READY_TO_POST)
                ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds ?: [-1]))
                ->when($restrict && ! $campIds, fn ($query) => $query->where(function ($access) use ($visibleCampIds) {
                    $access->whereNull('camp_id');
                    if ($visibleCampIds) {
                        $access->orWhereIn('camp_id', $visibleCampIds);
                    }
                }))
                ->latest('submitted_at')
                ->get()
            : collect();

        return [
            'pendingToleranceReviews' => $pending,
            'readyStockOpnames' => $ready,
        ];
    }
}
