<?php

namespace App\Services;

use App\Models\PurchaseRequestItem;
use App\Models\VendorQuotation;
use Illuminate\Validation\ValidationException;

class VendorQuotationService
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function hasPurchaseOrder(PurchaseRequestItem $item): bool
    {
        return $item->purchaseOrderItems()->exists();
    }

    public function normalizeCurrency(array $quotation, bool $canOverride): array
    {
        $currencyCode = strtoupper((string) ($quotation['currency_code'] ?? 'IDR'));
        if (! array_key_exists($currencyCode, $this->exchangeRates->currencies())) {
            throw ValidationException::withMessages(['quotations' => 'Mata uang penawaran tidak didukung.']);
        }

        if ($currencyCode === 'IDR') {
            return [
                ...$quotation,
                'currency_code' => 'IDR',
                'reference_exchange_rate' => 1,
                'exchange_rate' => 1,
                'exchange_rate_date' => $quotation['exchange_rate_date'] ?? now()->toDateString(),
                'exchange_rate_source' => 'system',
                'exchange_rate_override_reason' => null,
            ];
        }

        $referenceRate = (float) ($quotation['reference_exchange_rate'] ?? 0);
        $appliedRate = (float) ($quotation['exchange_rate'] ?? 0);
        if ($appliedRate <= 0) {
            throw ValidationException::withMessages(['quotations' => 'Kurs untuk '.$currencyCode.' wajib diisi.']);
        }

        $isManual = $referenceRate <= 0 || $this->ratesDiffer($appliedRate, $referenceRate);
        $reason = trim((string) ($quotation['exchange_rate_override_reason'] ?? ''));
        if ($isManual && ! $canOverride) {
            throw ValidationException::withMessages([
                'quotations' => 'Anda tidak memiliki hak untuk mengubah kurs secara manual.',
            ]);
        }
        if ($isManual && $reason === '') {
            throw ValidationException::withMessages([
                'quotations' => 'Alasan perubahan atau input kurs manual '.$currencyCode.' wajib diisi.',
            ]);
        }

        return [
            ...$quotation,
            'currency_code' => $currencyCode,
            'reference_exchange_rate' => $referenceRate > 0 ? $referenceRate : $appliedRate,
            'exchange_rate' => $appliedRate,
            'exchange_rate_date' => $quotation['exchange_rate_date'] ?? now()->toDateString(),
            'exchange_rate_source' => $isManual ? 'manual' : 'frankfurter',
            'exchange_rate_override_reason' => $isManual ? $reason : null,
        ];
    }

    public function resolveAllocations(PurchaseRequestItem $item, array $rawAllocations): array
    {
        $allocations = collect($rawAllocations)
            ->mapWithKeys(fn ($quantity, $quotationId) => [(int) $quotationId => (int) $quantity])
            ->filter(fn ($quantity) => $quantity > 0);

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Isi minimal satu qty vendor yang akan digunakan.',
            ]);
        }

        if ($allocations->sum() > $item->allocationLimit()) {
            throw ValidationException::withMessages([
                'allocations' => 'Total qty vendor tidak boleh melebihi batas tahap ini: '.$item->allocationLimit().' '.($item->uom ?? $item->inventoryItem->uom).'.',
            ]);
        }

        $quotations = $item->quotations->keyBy('id');
        foreach ($allocations as $quotationId => $quantity) {
            $quotation = $quotations->get($quotationId);
            if (! $quotation) {
                throw ValidationException::withMessages([
                    'allocations' => 'Ada penawaran vendor yang tidak valid.',
                ]);
            }

            if ($quantity > $quotation->available_quantity) {
                throw ValidationException::withMessages([
                    'allocations' => 'Alokasi '.$quotation->vendor->name.' melebihi stok vendor.',
                ]);
            }
        }

        return [$allocations, $quotations];
    }

    public function applyAllocations(PurchaseRequestItem $item, $allocations, $quotations): float
    {
        $item->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);

        $finalTotal = 0;
        foreach ($allocations as $quotationId => $quantity) {
            $quotation = $quotations->get($quotationId);
            $item->quotations()
                ->whereKey($quotationId)
                ->update(['is_selected' => true, 'selected_quantity' => $quantity]);
            $finalTotal += $quotation->totalIdr($quantity);
        }

        return $finalTotal;
    }

    public function refreshRecommendation(PurchaseRequestItem $item): void
    {
        $selectedQuotations = $item->selectedQuotations()->get();
        $selectedQuantity = (int) $selectedQuotations->sum('selected_quantity');
        $selectionIsValid = $selectedQuotations->isNotEmpty()
            && $selectedQuantity >= 1
            && $selectedQuantity <= $item->allocationLimit()
            && $selectedQuotations->every(fn (VendorQuotation $quotation) => (int) $quotation->selected_quantity <= (int) $quotation->available_quantity);

        if ($selectionIsValid) {
            $finalTotal = (float) $selectedQuotations->sum(
                fn (VendorQuotation $quotation) => $quotation->totalIdr((int) $quotation->selected_quantity)
            );
            $item->update([
                'final_total' => $finalTotal,
                'status' => $finalTotal > 0 ? 'vendor_selected' : $item->status,
                'director_approval_status' => 'not_required',
            ]);

            return;
        }

        $item->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);
        $item->update([
            'status' => 'ready_for_quotation',
            'final_total' => 0,
            'director_approval_status' => 'not_required',
        ]);
    }

    private function ratesDiffer(float $first, float $second): bool
    {
        return number_format($first, 4, '.', '') !== number_format($second, 4, '.', '');
    }
}
