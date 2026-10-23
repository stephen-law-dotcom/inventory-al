<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_REVISION_REQUIRED = 'revision_required';
    public const STATUS_READY_TO_POST = 'ready_to_post';
    public const STATUS_POSTED = 'posted';

    protected $guarded = [];

    protected $casts = [
        'opname_date' => 'date',
        'posted_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function officers()
    {
        return $this->belongsToMany(User::class, 'stock_opname_user')->withTimestamps();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISION_REQUIRED], true);
    }
}
