<x-filament-panels::page>
    <form wire:submit="adjust" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" />
    </form>
</x-filament-panels::page>
