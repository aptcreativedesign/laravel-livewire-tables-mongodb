<?php

namespace Rappasoft\LaravelLivewireTables\Traits;

use MongoDB\Laravel\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rappasoft\LaravelLivewireTables\Traits\Configuration\SortingConfiguration;
use Rappasoft\LaravelLivewireTables\Traits\Core\QueryStrings\HasQueryStringForSort;
use Rappasoft\LaravelLivewireTables\Traits\Helpers\SortingHelpers;
use Rappasoft\LaravelLivewireTables\Traits\Styling\HasSortingPillsStyling;

trait WithSorting
{
    use SortingConfiguration,
        SortingHelpers,
        HasQueryStringForSort,
        HasSortingPillsStyling;

    public array $sorts = [];

    public Collection $sortableColumns;

    public bool $sortingStatus = true;

    public bool $singleColumnSortingStatus = true;

    public bool $sortingPillsStatus = true;

    public ?string $defaultSortColumn = null;

    public string $defaultSortDirection = 'asc';

    public string $defaultSortingLabelAsc = 'A-Z';

    public string $defaultSortingLabelDesc = 'Z-A';

    public function mountWithSorting(): void
    {
        $this->setupDefaultSorting();
    }

    public function sortBy(string $columnSelectName): ?string
    {

        if ($this->sortingIsDisabled()) {
            return null;
        }

        // If single sorting is enabled and there are sorts but not the field that is being sorted,
        // then clear all the sorts
        if ($this->singleSortingIsEnabled() && $this->hasSorts() && ! $this->hasSort($columnSelectName)) {
            $this->clearSorts();
            $this->resetComputedPage();

        }

        if (! $this->hasSort($columnSelectName)) {
            $this->resetComputedPage();

            return $this->setSortAsc($columnSelectName);
        }

        if ($this->isSortAsc($columnSelectName)) {
            $this->resetComputedPage();

            return $this->setSortDesc($columnSelectName);
        }

        $this->clearSort($columnSelectName);

        return null;
    }

    public function applySorting(): Builder
    {

        $allCols = $this->getColumns();

        foreach ($this->getSorts() as $column => $direction) {
            if (! in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }
            $tmpCol = $column;
            $column = $this->getColumnBySelectName($tmpCol);

            if (is_null($column)) {
                foreach ($allCols as $cols) {
                    if ($cols->getSlug() == $tmpCol && $cols->hasSortCallback()) {
                        $this->setBuilder(call_user_func($cols->getSortCallback(), $this->getBuilder(), $direction));

                        continue;
                    }
                }

                continue;
            }

            if (! $column->isSortable()) {
                continue;
            }

            // TODO: Test
            if ($column->hasSortCallback()) {
                $this->setBuilder(call_user_func($column->getSortCallback(), $this->getBuilder(), $direction));
            } elseif ($column->isBaseColumn()) {
                $this->setBuilder($this->getBuilder()->orderBy($column->getColumnSelectName(), $direction));
            } else {
//                $value = $this->getBuilder()->getGrammar()->wrap($column->getColumn().' as '.$column->getColumnSelectName());
                $value = $this->getBuilder()->getGrammar()->wrap($column->getColumn());
                $segments = preg_split('/\s+as\s+/i', $value);
                $this->setBuilder($this->getBuilder()->orderByRaw($segments[1].' '.$direction));
            }
        }

        return $this->getBuilder();
    }
}
