<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\OperationalReportExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Services\InventoryPageContextService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly InventoryPageContextService $pages,
    ) {}

    public function index(?string $reportType = null)
    {
        $this->ensurePermission('reports.view');
        $reportType = $this->validatedReportType($reportType ?? request('report', 'requests'));
        $this->ensureReportPermission($reportType);
        $scope = $this->pages->scope();
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];

        return view('inventory.reports.index', array_merge($this->pages->base('reports', $scope), [
            'activeReport' => $reportType,
            'reportDataset' => $this->reports->build($reportType, auth()->user(), request()->query()),
            'camps' => $scope['camps'],
            'units' => Unit::query()
                ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('code')
                ->get(),
            'items' => InventoryItem::orderBy('name')->get(),
            'vendors' => Vendor::orderBy('name')->get(),
        ]));
    }

    public function export(Request $request, string $reportType, string $format)
    {
        $this->ensurePermission('reports.view');
        $reportType = $this->validatedReportType($reportType);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $this->ensureReportPermission($reportType);

        $report = $this->reports->build($reportType, auth()->user(), $request->query(), false);
        $filename = 'report-'.str_replace('_', '-', $reportType).'-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            return Pdf::loadView('inventory.reports.export-pdf', compact('report'))
                ->setPaper('a4', count($report['columns']) > 7 ? 'landscape' : 'portrait')
                ->download($filename.'.pdf');
        }

        $export = new OperationalReportExport(
            $this->reports->exportHeadings($report),
            $this->reports->exportRows($report),
        );

        return Excel::download(
            $export,
            $filename.'.'.$format,
            $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX,
        );
    }

    private function validatedReportType(string $reportType): string
    {
        $reportType = str_replace('-', '_', $reportType);
        abort_unless(array_key_exists($reportType, $this->reportTypes()), 404);

        return $reportType;
    }

    private function ensureReportPermission(string $reportType): void
    {
        if ($reportType === 'activity_logs') {
            $this->ensurePermission('reports.activity_logs');
        }
        if ($reportType === 'stock_movements') {
            $this->ensurePermission('reports.stock_movements');
        }
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function reportTypes(): array
    {
        return [
            'procurement_flow' => 'Proses Pengadaan',
            'requests' => 'Order Request',
            'vendors' => 'Vendor',
            'purchase_orders' => 'Purchase Order',
            'receipts' => 'Penerimaan',
            'credit_notes' => 'Credit Note',
            'stock' => 'Stok',
            'stock_locations' => 'Stok per Lokasi',
            'outstanding_po' => 'Outstanding PO',
            'usage' => 'Pemakaian',
            'dispatches' => 'Pengiriman Site',
            'opnames' => 'Stock Opname',
            'stock_movements' => 'Kartu Stok',
            'inventory_planning' => 'EOQ / ROP',
            'activity_logs' => 'Log User',
        ];
    }
}
