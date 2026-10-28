<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_sequences_continue_across_months_with_month_year_in_number(): void
    {
        $camp = $this->createCamp();

        try {
            Carbon::setTestNow('2026-01-31 10:00:00');
            $this->assertSame('001/AML-OR/01-26', DocumentNumberService::nextOrderRequest($camp));
            $this->assertSame('001/AML-CN/01-26', DocumentNumberService::nextCreditNote($camp));
            $this->assertSame('001/AML-SPB/01-26', DocumentNumberService::nextSiteDispatch($camp));
            $this->assertSame('001/AML-GR/01-26', DocumentNumberService::nextGoodsReceipt());
            $this->assertSame('001/AML-SR/01-26', DocumentNumberService::nextSiteReceipt());
            $this->assertSame('001/AML-SO/01-26', DocumentNumberService::nextStockOpname());

            Carbon::setTestNow('2026-02-01 10:00:00');
            $this->assertSame('002/AML-OR/02-26', DocumentNumberService::nextOrderRequest($camp));
            $this->assertSame('002/AML-CN/02-26', DocumentNumberService::nextCreditNote($camp));
            $this->assertSame('002/AML-SPB/02-26', DocumentNumberService::nextSiteDispatch($camp));
            $this->assertSame('002/AML-GR/02-26', DocumentNumberService::nextGoodsReceipt());
            $this->assertSame('002/AML-SR/02-26', DocumentNumberService::nextSiteReceipt());
            $this->assertSame('002/AML-SO/02-26', DocumentNumberService::nextStockOpname());

            Carbon::setTestNow('2027-01-01 10:00:00');
            $this->assertSame('001/AML-OR/01-27', DocumentNumberService::nextOrderRequest($camp));
            $this->assertSame('001/AML-CN/01-27', DocumentNumberService::nextCreditNote($camp));
            $this->assertSame('001/AML-SPB/01-27', DocumentNumberService::nextSiteDispatch($camp));
            $this->assertSame('001/AML-GR/01-27', DocumentNumberService::nextGoodsReceipt());
            $this->assertSame('001/AML-SR/01-27', DocumentNumberService::nextSiteReceipt());
            $this->assertSame('001/AML-SO/01-27', DocumentNumberService::nextStockOpname());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_purchase_order_number_supports_new_and_legacy_order_request_formats(): void
    {
        $this->assertSame(
            '005-1/AML-OR/01-26',
            DocumentNumberService::purchaseOrderNumber('005/AML-OR/01-26', 1, true)
        );
        $this->assertSame(
            '005-1/AML-OR/01-26',
            DocumentNumberService::purchaseOrderNumber('005/AML-OR/01-26', 1, false)
        );
        $this->assertSame(
            '005-R-1/AML-OR/01-26',
            DocumentNumberService::purchaseOrderNumber('005/AML-OR/01-26', 1, false, true)
        );
        $this->assertSame(
            '005-2/AML-OR/26',
            DocumentNumberService::purchaseOrderNumber('005/AML-OR/26', 2, true)
        );
    }

    private function createCamp(): Camp
    {
        $company = Company::create([
            'code' => 'AML',
            'name' => 'PT Adimitra Lestari',
            'order_request_code' => 'AML-OR',
        ]);

        return Camp::create([
            'code' => 'SITE-TEST',
            'name' => 'Site Test',
            'company_id' => $company->id,
            'company_name' => $company->name,
        ]);
    }
}
