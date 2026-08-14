<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceController extends Controller
{
    public function print(Invoice $invoice, SettingsRepository $settings): View
    {
        Gate::authorize('view', $invoice);

        return view('invoices.document', ['invoice' => $invoice->load('sale.customer', 'sale.lines.platform'), 'settings' => $settings, 'print' => true]);
    }

    public function pdf(Invoice $invoice, SettingsRepository $settings): Response
    {
        Gate::authorize('view', $invoice);
        $invoice->load('sale.customer', 'sale.lines.platform');

        return Pdf::loadView('invoices.document', compact('invoice', 'settings'))->setPaper('a4')->download($invoice->invoice_number.'.pdf');
    }
}
