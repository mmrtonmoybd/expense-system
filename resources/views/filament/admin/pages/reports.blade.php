<x-filament-panels::page>
    <form wire:submit="apply" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::actions :actions="$this->getFormActions()" />
            <span wire:loading wire:target="apply" class="text-sm text-gray-500 dark:text-gray-400">
                Updating report…
            </span>
        </div>
    </form>

    @if ($report)
        @include('filament.admin.partials.report-result', [
            'report' => $report,
            'eyebrow' => 'Financial report',
            'reportPage' => $reportPage,
            'paginated' => true,
            'loadingTargets' => 'apply,previousReportPage,nextReportPage',
            'subtitle' =>
                Illuminate\Support\Carbon::parse($data['from'])->format(config('app.date_format')) .
                ' – ' .
                Illuminate\Support\Carbon::parse($data['to'])->format(config('app.date_format')) .
                ($report['pagination'] ? ' · ' . number_format($report['pagination']['total']) . ' matching records' : ''),
        ])
    @endif
</x-filament-panels::page>
