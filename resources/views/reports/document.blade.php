<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>{{ $report['title'] }}</title>
        <style>
            @page {
                margin: 24px;
            }
            body {
                font:
                    11px DejaVu Sans,
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
        </style>
    </head>
    <body @if($print??false) onload="window.print()" @endif>
        @if ($print ?? false)
            <button class="no-print" onclick="window.print()">Print</button>
        @endif

        <h2>{{ $settings->get('business_name', config('app.name')) }}</h2>
        <h1>{{ $report['title'] }}</h1>
        <div class="meta">
            Period:
            {{ filled($filters['from'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['from'])->format(config('app.date_format')) : 'Beginning' }}
            –
            {{ filled($filters['to'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['to'])->format(config('app.date_format')) : 'Today' }}
            · Generated {{ now()->format(config('app.datetime_format')) }}
        </div>
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
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['headings']) }}">No matching records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>
