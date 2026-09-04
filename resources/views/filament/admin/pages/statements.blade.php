<x-filament-panels::page>
    <form wire:submit="apply" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::actions :actions="$this->getFormActions()" />
            <span wire:loading wire:target="apply" class="text-sm text-gray-500 dark:text-gray-400">
                Building statement…
            </span>
        </div>
    </form>

    @if ($report)
        @include('filament.admin.partials.report-result', [
            'report' => $report,
            'eyebrow' => 'Statement',
            'reportPage' => 1,
            'paginated' => false,
            'loadingTargets' => 'apply',
            'subtitle' =>
                Illuminate\Support\Carbon::parse($data['from'])->format(config('app.date_format')) .
                ' – ' .
                Illuminate\Support\Carbon::parse($data['to'])->format(config('app.date_format')),
        ])
    @endif
</x-filament-panels::page>
