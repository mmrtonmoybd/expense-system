@php
    $eyebrow ??= 'Financial report';
    $subtitle ??= null;
    $reportPage ??= 1;
    $paginated ??= false;
    $loadingTargets ??= 'apply';
@endphp

<section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div
        class="flex flex-col gap-4 border-b border-gray-200 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between dark:border-white/10"
    >
        <div>
            <p class="text-primary-600 dark:text-primary-400 text-xs font-semibold tracking-wider uppercase">
                {{ $eyebrow }}
            </p>
            <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $report['title'] }}</h2>
            @if ($subtitle)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>

        @can('Export:Report')
            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" :href="$this->exportUrl('xlsx')" icon="heroicon-o-table-cells" size="sm">
                    XLSX
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="$this->exportUrl('csv')"
                    icon="heroicon-o-document-text"
                    color="gray"
                    size="sm"
                >
                    CSV
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="$this->exportUrl('pdf')"
                    icon="heroicon-o-document-arrow-down"
                    color="gray"
                    size="sm"
                >
                    PDF
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="$this->exportUrl('print')"
                    target="_blank"
                    icon="heroicon-o-printer"
                    color="gray"
                    size="sm"
                >
                    Print
                </x-filament::button>
            </div>
        @endcan
    </div>

    @if (! empty($report['totals']))
        <dl class="grid gap-px border-b border-gray-200 bg-gray-200 sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10 dark:bg-white/10">
            @foreach ($report['totals'] as $totalKey => $totalValue)
                <div class="bg-gray-50/80 px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {{ Illuminate\Support\Str::headline((string) $totalKey) }}
                    </dt>
                    <dd class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                        {{ is_numeric($totalValue) ? number_format((float) $totalValue, 2) : $totalValue }}
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    <div wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}" class="overflow-x-auto transition-opacity">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50/80 dark:bg-white/5">
                <tr>
                    @foreach ($report['headings'] as $heading)
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold tracking-wide whitespace-nowrap text-gray-600 uppercase dark:text-gray-300"
                        >
                            {{ $heading }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
                @forelse ($report['rows'] as $rowIndex => $row)
                    <tr
                        wire:key="report-row-{{ $reportPage }}-{{ $rowIndex }}"
                        class="hover:bg-primary-50/40 transition dark:hover:bg-white/5"
                    >
                        @foreach ($row as $value)
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-14 text-center" colspan="{{ count($report['headings']) }}">
                            <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-8 w-8" />
                                <p class="font-medium text-gray-700 dark:text-gray-200">No matching records</p>
                                <p class="text-sm">Try changing the selected filters or date range.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($paginated && $report['pagination'] && $report['pagination']['last_page'] > 1)
        <div
            class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-white/10"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Showing
                {{ number_format($report['pagination']['from']) }}–{{ number_format($report['pagination']['to']) }}
                of {{ number_format($report['pagination']['total']) }}
            </p>
            <div class="flex gap-2">
                <x-filament::button
                    wire:click="previousReportPage"
                    wire:loading.attr="disabled"
                    :disabled="$report['pagination']['current_page'] <= 1"
                    icon="heroicon-m-chevron-left"
                    color="gray"
                    size="sm"
                >
                    Previous
                </x-filament::button>
                <x-filament::button
                    wire:click="nextReportPage"
                    wire:loading.attr="disabled"
                    :disabled="$report['pagination']['current_page'] >= $report['pagination']['last_page']"
                    icon-after="heroicon-m-chevron-right"
                    color="gray"
                    size="sm"
                >
                    Next
                </x-filament::button>
            </div>
        </div>
    @endif
</section>
