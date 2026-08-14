<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ReportDataBuilder;
use App\Support\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as WriterType;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

final class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $format, ReportDataBuilder $builder, SettingsRepository $settings): Response
    {
        abort_unless($request->user()?->can('Export:Report'), 403);
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf', 'print'], true), 404);

        $filters = $request->validate([
            'report' => ['nullable', 'in:sales,purchases,inventory,platforms,customers,expenses,payroll,pnl,audit'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
        $report = $builder->build($filters);
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His');
        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($report), $filename.'.xlsx', WriterType::XLSX);
        }
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($report): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $report['headings']);
                foreach ($report['rows'] as $row) {
                    fputcsv($output, $row);
                }fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        if ($format === 'print') {
            return response()->view('reports.document', ['report' => $report, 'filters' => $filters, 'settings' => $settings, 'print' => true]);
        }

        return Pdf::loadView('reports.document', compact('report', 'filters', 'settings'))->setPaper('a4', count($report['headings']) > 7 ? 'landscape' : 'portrait')->download($filename.'.pdf');
    }
}
