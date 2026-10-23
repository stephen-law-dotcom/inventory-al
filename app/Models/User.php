<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role_id',
        'company_ids',
        'camp_ids',
        'password',
        'signature_image',
        'signature_method',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'company_ids' => 'array',
            'camp_ids' => 'array',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockOpnames()
    {
        return $this->belongsToMany(StockOpname::class, 'stock_opname_user')->withTimestamps();
    }

    public function systemNotifications()
    {
        return $this->hasMany(SystemNotification::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_admin;
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) $this->role?->hasPermission($permission);
    }

    public function hasRole(string|array $slugs): bool
    {
        $slugs = (array) $slugs;

        return in_array($this->role?->slug, $slugs, true);
    }

    public function canSeePrices(): bool
    {
        return $this->hasPermission('prices.view');
    }

    public function canManageMasterData(): bool
    {
        return $this->hasPermission('master.manage');
    }

    public function canApproveEquipment(): bool
    {
        return $this->hasPermission('approvals.equipment');
    }

    public function canApprovePpic(): bool
    {
        return $this->hasPermission('approvals.ppic');
    }

    public function canApproveDirector(): bool
    {
        return $this->hasPermission('approvals.director');
    }

    public function canApproveLogistic(): bool
    {
        return $this->hasPermission('approvals.logistic');
    }

    public function canApproveChiefMechanic(): bool
    {
        return $this->hasPermission('approvals.chief_mechanic');
    }

    public function canReviewStockOpname(): bool
    {
        return $this->hasPermission('stock_opname.review');
    }

    public function canPostStockOpname(): bool
    {
        return $this->hasPermission('stock_opname.post');
    }

    public function canApproveManagerCamp(): bool
    {
        return $this->hasPermission('approvals.manager_camp');
    }

    public function canManageWarehouse(): bool
    {
        return $this->hasPermission('receipts.manage')
            || $this->hasPermission('dispatches.manage')
            || $this->hasPermission('stock_opname.manage');
    }

    public function canManageVendorPrices(): bool
    {
        return $this->hasPermission('vendor_prices.manage');
    }

    public function scopedCompanyIds(): array
    {
        return collect($this->company_ids ?? [])->filter()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function scopedCampIds(): array
    {
        return collect($this->camp_ids ?? [])->filter()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function canAccessCompany(?int $companyId): bool
    {
        if ($this->isAdmin() || count($this->scopedCompanyIds()) === 0) {
            return true;
        }

        return $companyId && in_array($companyId, $this->scopedCompanyIds(), true);
    }

    public function canAccessCamp(?int $campId, ?int $companyId = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (count($this->scopedCampIds()) > 0) {
            return $campId && in_array($campId, $this->scopedCampIds(), true);
        }

        return $this->canAccessCompany($companyId);
    }

    public function roleName(): string
    {
        return $this->role?->name ?? 'Tanpa Role';
    }

    public function defaultRouteName(): string
    {
        $routes = [
            'dashboard.view' => 'dashboard',
            'master.view' => 'master.camps',
            'requests.view' => 'requests',
            'approvals.view' => 'approvals',
            'vendor_prices.view' => 'vendor-prices',
            'purchase_orders.view' => 'purchase-orders',
            'receipts.view' => 'receipts',
            'credit_notes.view' => 'credit-notes',
            'dispatches.view' => 'dispatches',
            'usage.view' => 'usage',
            'stock.view' => 'stock',
            'stock_opname.view' => 'stock-opname',
            'reports.view' => 'reports',
        ];

        foreach ($routes as $permission => $routeName) {
            if ($this->hasPermission($permission)) {
                return $routeName;
            }
        }

        return 'profile.edit';
    }
}
