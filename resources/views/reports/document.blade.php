<!DOCTYPE html>
<html lang="{{ \App\Support\Localization::tag() }}">
    <head>
        <meta charset="utf-8" />
        <title>{{ $report['title'] }}</title>
        <style>
            @font-face {
                font-family: 'notosansbengali';
                font-style: normal;
                font-weight: 400;
                src: url('{{ \App\Support\Localization::documentFontPath() }}') format('truetype');
            }

            @font-face {
                font-family: 'notosansbengali';
                font-style: normal;
                font-weight: 700;
                src: url('{{ \App\Support\Localization::documentFontPath('Bold') }}') format('truetype');
            }

            @page {
                margin: 24px;
            }
            body {
                font:
                    11px 'notosansbengali',
                    'Noto Sans Bengali',
                    DejaVu Sans,
                    sans-serif;
                color: #172033;
            }
            h1 {
                color: #435cea;
            }
            .meta {
                color: #667085;
                margin-bottom: 16px;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            th,
            td {
                border: 1px solid #d8ddec;
                padding: 6px;
                text-align: left;
            }
            th {
                background: #eef0ff;
            }
            .no-print {
                margin-bottom: 12px;
            }
            @media print {
                .no-print {
                    display: none;
                }
            }

            @if (\App\Support\Localization::isBengali())
                /* Bengali clusters break under letter-spacing; see the invoice document. */
                th,
                h1,
                h2 {
                    letter-spacing: normal;
                    text-transform: none;
                }
            @endif
        </style>
    </head>
    <body @if($print??false) onload="window.print()" @endif>
        @if ($print ?? false)
            <button class="no-print" onclick="window.print()">{{ __('documents.report.print') }}</button>
        @endif

        <h2>{{ $settings->get('business_name', config('app.name')) }}</h2>
        <h1>{{ $report['title'] }}</h1>
        <div class="meta">
            {{ __('documents.report.period') }}:
            {{ filled($filters['from'] ?? null) ? \App\Support\Localization::date($filters['from']) : __('documents.report.beginning') }}
            –
            {{ filled($filters['to'] ?? null) ? \App\Support\Localization::date($filters['to']) : __('documents.report.today') }}
            · {{ __('documents.report.generated', ['at' => \App\Support\Localization::dateTime(now())]) }}
        </div>

        @if (! empty($report['totals']))
            <table class="totals">
                <thead>
                    <tr>
                        @foreach (array_keys($report['totals']) as $totalKey)
                            <th>{{ \Illuminate\Support\Str::headline((string) $totalKey) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($report['totals'] as $totalValue)
                            <td>
                                {{ is_numeric($totalValue) ? \App\Support\Localization::number($totalValue) : $totalValue }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach ($report['headings'] as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ is_numeric($value) ? \App\Support\Localization::number($value) : $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['headings']) }}">{{ __('documents.report.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>
