<x-filament-panels::page>
    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" />
    </form>
    @if ($result)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['available_usd' => 'Available USD', 'weighted_cost' => 'Weighted cost', 'selling_rate' => 'Selling rate', 'revenue' => 'Revenue', 'inventory_cost' => 'Inventory cost', 'gross_profit' => 'Gross profit', 'profit_per_usd' => 'Profit / USD', 'gross_margin' => 'Margin %', 'remaining_usd' => 'Remaining USD', 'required_rate' => 'Required rate'] as $key => $label)
                @if (array_key_exists($key, $result))
                    <div
                        class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                    >
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                        <div class="mt-2 text-xl font-semibold">{{ $result[$key] }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
