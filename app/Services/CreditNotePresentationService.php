<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use Illuminate\Support\Collection;

class CreditNotePresentationService
{
    public function timeline(CreditNote $creditNote): Collection
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($creditNote) {
                $query->where(function ($subjectQuery) use ($creditNote) {
                    $subjectQuery
                        ->where('subject_type', $creditNote->getMorphClass())
                        ->where('subject_id', $creditNote->id);
                })->orWhere(function ($itemQuery) use ($creditNote) {
                    $itemQuery
                        ->where('subject_type', (new CreditNoteItem)->getMorphClass())
                        ->whereIn('subject_id', $creditNote->items->pluck('id'));
                });
            })
            ->oldest('created_at')
            ->get();
    }
}
