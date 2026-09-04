<?php

declare(strict_types=1);

namespace App\Filament\Admin\Concerns;

/**
 * Hands the table's live state to the summary widgets above it.
 *
 * Widgets that use InteractsWithPageTable declare the table state as #[Reactive] properties,
 * but Livewire resolves a reactive property to null unless the parent passed it by name when
 * the child was mounted. Filament only passes getWidgetData(), so without this the widgets
 * receive null on every re-render — which fatals on the non-nullable ones such as
 * $tableColumnSearches, and silently drops the filters on the rest.
 */
trait PassesTableStateToWidgets
{
    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return [
            ...parent::getWidgetData(),
            'activeTab' => property_exists($this, 'activeTab') ? $this->activeTab : null,
            'paginators' => $this->paginators,
            'tableColumnSearches' => $this->tableColumnSearches,
            'tableFilters' => $this->tableFilters,
            'tableGrouping' => $this->tableGrouping,
            'tableRecordsPerPage' => $this->tableRecordsPerPage,
            'tableSearch' => $this->tableSearch,
            'tableSort' => $this->tableSort,
        ];
    }
}
