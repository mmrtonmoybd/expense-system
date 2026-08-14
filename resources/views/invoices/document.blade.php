<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ $invoice->invoice_number }}</title>
        <style>
            @page {
                margin: 30px 34px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                color: #172033;
                background: #f4f6fc;
                font: 13px DejaVu Sans, sans-serif;
                line-height: 1.55;
            }

            .sheet {
                position: relative;
                max-width: 900px;
                min-height: 980px;
                margin: 24px auto;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 16px 50px rgba(35, 48, 88, 0.13);
            }

            .accent {
                height: 8px;
                background: #435cea;
            }

            .content {
                padding: 34px 38px 28px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            .brand-table td,
            .parties td,
            .summary td,
            .closing td {
                vertical-align: top;
            }

            .brand-name {
                margin: 8px 0 3px;
                color: #172033;
                font-size: 20px;
                font-weight: 700;
            }

            .logo {
                max-width: 170px;
                max-height: 65px;
            }

            .invoice-title {
                margin: 0;
                color: #435cea;
                font-size: 34px;
                font-weight: 800;
                letter-spacing: 2px;
            }

            .invoice-number {
                margin-top: 4px;
                font-size: 15px;
                font-weight: 700;
            }

            .muted {
                color: #667085;
            }

            .meta-label,
            .section-label {
                color: #667085;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 1px;
                text-transform: uppercase;
            }

            .status {
                display: inline-block;
                margin-top: 9px;
                border-radius: 14px;
                padding: 4px 11px;
                color: #166534;
                background: #dcfce7;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.7px;
                text-transform: uppercase;
            }

            .divider {
                height: 1px;
                margin: 26px 0;
                background: #e7eaf3;
            }

            .party-card {
                width: 48%;
                border: 1px solid #e7eaf3;
                border-radius: 10px;
                padding: 15px 17px;
                background: #fafbff;
            }

            .party-card.right {
                margin-left: 4%;
            }

            .party-name {
                margin: 5px 0 2px;
                color: #172033;
                font-size: 15px;
                font-weight: 700;
            }

            .items {
                margin-top: 26px;
            }

            .items th {
                padding: 11px 10px;
                color: #fff;
                background: #293b9f;
                font-size: 10px;
                letter-spacing: 0.6px;
                text-align: right;
                text-transform: uppercase;
            }

            .items th:first-child {
                border-radius: 7px 0 0 7px;
            }

            .items th:last-child {
                border-radius: 0 7px 7px 0;
            }

            .items td {
                border-bottom: 1px solid #e7eaf3;
                padding: 13px 10px;
                text-align: right;
            }

            .items tbody tr:nth-child(even) {
                background: #fafbff;
            }

            .items th:first-child,
            .items td:first-child,
            .items th:nth-child(2),
            .items td:nth-child(2) {
                text-align: left;
            }

            .line-number {
                width: 32px;
                color: #98a2b3;
            }

            .summary {
                margin-top: 18px;
            }

            .summary-spacer {
                width: 52%;
            }

            .summary-box {
                width: 48%;
                border: 1px solid #dfe4f4;
                border-radius: 10px;
                overflow: hidden;
                background: #f8f9ff;
            }

            .summary-row td {
                padding: 8px 13px;
            }

            .grand-total td {
                padding: 12px 13px;
                color: #fff;
                background: #435cea;
                font-size: 16px;
                font-weight: 700;
            }

            .align-right {
                text-align: right;
            }

            .notes {
                margin-top: 24px;
                border-left: 3px solid #435cea;
                padding: 10px 14px;
                background: #f7f8fe;
            }

            .closing {
                margin-top: 34px;
            }

            .terms {
                width: 62%;
                padding-right: 30px;
            }

            .signature {
                width: 38%;
                padding-top: 30px;
                text-align: center;
            }

            .signature-line {
                border-top: 1px solid #98a2b3;
                padding-top: 7px;
            }

            .footer {
                margin-top: 34px;
                border-top: 1px solid #e7eaf3;
                padding-top: 13px;
                color: #667085;
                font-size: 10px;
                text-align: center;
            }

            .print-button {
                position: fixed;
                top: 18px;
                right: 18px;
                z-index: 10;
                border: 0;
                border-radius: 8px;
                padding: 10px 16px;
                color: #fff;
                background: #435cea;
                font: 600 13px sans-serif;
                cursor: pointer;
                box-shadow: 0 6px 20px rgba(67, 92, 234, 0.3);
            }

            @media print {
                body {
                    background: #fff;
                }

                .sheet {
                    min-height: 0;
                    margin: 0;
                    box-shadow: none;
                }

                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body @if ($print ?? false) onload="window.print()" @endif>
        @if ($print ?? false)
            <button class="print-button no-print" type="button" onclick="window.print()">Print invoice</button>
        @endif

        <main class="sheet">
            <div class="accent"></div>
            <div class="content">
                <table class="brand-table">
                    <tr>
                        <td style="width: 62%">
                            @if ($settings->get('business_logo'))
                                <img
                                    class="logo"
                                    src="{{ ($print ?? false) ? asset('storage/' . $settings->get('business_logo')) : public_path('storage/' . $settings->get('business_logo')) }}"
                                    alt="{{ $settings->get('business_name', config('app.name')) }}"
                                />
                            @endif
                            <div class="brand-name">{{ $settings->get('business_name', config('app.name')) }}</div>
                            @if ($settings->get('business_address'))
                                <div class="muted">{{ $settings->get('business_address') }}</div>
                            @endif
                            <div class="muted">
                                {{ $settings->get('business_phone') }}
                                @if ($settings->get('business_email'))
                                    · {{ $settings->get('business_email') }}
                                @endif
                            </div>
                            @if ($settings->get('business_website'))
                                <div class="muted">{{ $settings->get('business_website') }}</div>
                            @endif
                        </td>
                        <td class="align-right" style="width: 38%">
                            <h1 class="invoice-title">INVOICE</h1>
                            <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
                            <span class="status">{{ $invoice->status }}</span>
                        </td>
                    </tr>
                </table>

                <div class="divider"></div>

                <table class="parties">
                    <tr>
                        <td class="party-card">
                            <div class="section-label">Billed to</div>
                            <div class="party-name">{{ $invoice->sale->customer->name }}</div>
                            @if ($invoice->sale->customer->company_name)
                                <div>{{ $invoice->sale->customer->company_name }}</div>
                            @endif
                            <div class="muted">{{ $invoice->sale->customer->phone }}</div>
                            @if ($invoice->sale->customer->email)
                                <div class="muted">{{ $invoice->sale->customer->email }}</div>
                            @endif
                            @if ($invoice->sale->customer->address)
                                <div class="muted">{{ $invoice->sale->customer->address }}</div>
                            @endif
                        </td>
                        <td class="party-card right">
                            <div class="section-label">Invoice details</div>
                            <table style="margin-top: 6px">
                                <tr>
                                    <td class="muted">Invoice date</td>
                                    <td class="align-right"><strong>{{ $invoice->invoice_date->format(config('app.date_format')) }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="muted">Sale reference</td>
                                    <td class="align-right"><strong>{{ $invoice->sale->sale_number }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="muted">Currency</td>
                                    <td class="align-right"><strong>BDT</strong></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="items">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Platform</th>
                            <th>USD Quantity</th>
                            <th>Rate (BDT/USD)</th>
                            <th>Amount (BDT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->sale->lines as $line)
                            <tr>
                                <td class="line-number">{{ $loop->iteration }}</td>
                                <td><strong>{{ $line->platform->name }}</strong></td>
                                <td>{{ number_format((float) $line->usd_quantity, 2) }}</td>
                                <td>{{ number_format((float) $line->selling_rate, 2) }}</td>
                                <td><strong>{{ number_format((float) $line->revenue, 2) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="summary">
                    <tr>
                        <td class="summary-spacer"></td>
                        <td class="summary-box">
                            <table>
                                <tr class="summary-row">
                                    <td class="muted">Total USD</td>
                                    <td class="align-right"><strong>{{ number_format((float) $invoice->sale->total_usd, 2) }}</strong></td>
                                </tr>
                                <tr class="grand-total">
                                    <td>Grand Total</td>
                                    <td class="align-right">BDT {{ number_format((float) $invoice->sale->total_bdt_revenue, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                @if ($invoice->sale->note)
                    <div class="notes">
                        <div class="section-label">Note</div>
                        <div>{{ $invoice->sale->note }}</div>
                    </div>
                @endif

                <table class="closing">
                    <tr>
                        <td class="terms">
                            @if ($settings->get('invoice_terms'))
                                <div class="section-label">Terms & conditions</div>
                                <div class="muted" style="margin-top: 6px">{{ $settings->get('invoice_terms') }}</div>
                            @endif
                        </td>
                        <td class="signature">
                            <div class="signature-line">
                                <strong>{{ $settings->get('authorized_person') }}</strong>
                                <br />
                                <span class="muted">{{ $settings->get('signature_label', 'Authorized signature') }}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="footer">
                    {{ $settings->get('invoice_footer', 'Thank you for your business.') }}
                    <br />
                    Invoice {{ $invoice->invoice_number }} · Generated {{ now()->format(config('app.datetime_format')) }}
                </div>
            </div>
        </main>
    </body>
</html>
