<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Normalizes controlled DataGrid query state and applies only server-approved sorting/search rules.
 */
final class DataGridRequest
{
    /**
     * Create an immutable normalized DataGrid request state.
     *
     * @param array<int, string> $filterKeys
     * @param array<int, string> $sortKeys
     * @param array<int, array{id:string,desc?:bool}> $defaultSorting
     */
    private function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly string $search,
        public readonly array $sorting,
        public readonly array $filters,
        private readonly array $sortKeys,
        private readonly array $filterKeys,
        private readonly array $defaultSorting,
    ) {}

    /**
     * Parse standard DataGrid query parameters while rejecting unknown sort/filter identifiers.
     *
     * Query contract:
     * - page=1
     * - per_page=25
     * - search=alice
     * - sort=name,-created_at
     * - filters[status]=active
     * - filters[created_at][from]=2026-08-01
     * - filters[created_at][to]=2026-08-31
     *
     * @param array<int, string> $sortKeys
     * @param array<int, string> $filterKeys
     * @param array<int, array{id:string,desc?:bool}> $defaultSorting
     */
    public static function from(
        Request $request,
        array $sortKeys,
        array $filterKeys = [],
        array $defaultSorting = [],
        int $defaultPageSize = 25,
        int $maxPageSize = 100,
    ): self {
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(max(5, (int) $request->query('per_page', $defaultPageSize)), max(5, $maxPageSize));
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 500));
        $requestedSort = array_filter(array_map('trim', explode(',', (string) $request->query('sort', ''))));
        $sorting = [];

        foreach ($requestedSort as $item) {
            $desc = str_starts_with($item, '-');
            $id = ltrim($item, '+-');
            if ($id !== '' && in_array($id, $sortKeys, true)) {
                $sorting[] = ['id' => $id, 'desc' => $desc];
            }
            if (count($sorting) >= 3) break;
        }

        if ($sorting === []) {
            $sorting = array_values(array_filter($defaultSorting, static fn (array $sort): bool => isset($sort['id']) && in_array($sort['id'], $sortKeys, true)));
        }

        $rawFilters = $request->query('filters', []);
        if (! is_array($rawFilters)) $rawFilters = [];
        $filters = [];
        foreach ($filterKeys as $key) {
            if (! array_key_exists($key, $rawFilters)) continue;
            $filters[$key] = self::normalizeFilterValue($rawFilters[$key]);
        }

        return new self($page, $pageSize, $search, $sorting, $filters, array_values($sortKeys), array_values($filterKeys), $defaultSorting);
    }

    /**
     * Apply global search across an explicit list of database columns.
     *
     * @param array<int, string> $columns
     */
    public function applySearch(Builder $query, array $columns): Builder
    {
        if ($this->search === '' || $columns === []) return $query;
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search).'%';

        return $query->where(function (Builder $nested) use ($columns, $term): void {
            foreach (array_values($columns) as $index => $column) {
                if ($index === 0) $nested->where($column, 'like', $term);
                else $nested->orWhere($column, 'like', $term);
            }
        });
    }

    /**
     * Apply normalized sorting using a UI-column-to-database-column whitelist.
     *
     * @param array<string, string> $columnMap
     */
    public function applySorting(Builder $query, array $columnMap): Builder
    {
        foreach ($this->sorting as $sort) {
            $column = $columnMap[$sort['id']] ?? null;
            if (! $column) continue;
            $query->orderBy($column, ($sort['desc'] ?? false) ? 'desc' : 'asc');
        }
        return $query;
    }

    /** Return one sanitized scalar filter value or the provided fallback. */
    public function filter(string $key, mixed $fallback = null): mixed
    {
        return in_array($key, $this->filterKeys, true) ? ($this->filters[$key] ?? $fallback) : $fallback;
    }

    /** Return one normalized date-range filter with optional from/to ISO dates. */
    public function dateRange(string $key): array
    {
        $value = $this->filter($key, []);
        if (! is_array($value)) return [];
        return array_filter([
            'from' => self::isoDate($value['from'] ?? null),
            'to' => self::isoDate($value['to'] ?? null),
        ]);
    }

    /** Return the canonical query shape used by JSON list endpoints. */
    public function meta(int $total): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->pageSize,
            'total' => max(0, $total),
            'last_page' => max(1, (int) ceil(max(0, $total) / $this->pageSize)),
            'search' => $this->search,
            'sorting' => $this->sorting,
            'filters' => $this->filters,
        ];
    }

    /** Normalize nested or scalar filter values to bounded, JSON-safe input. */
    private static function normalizeFilterValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 10, true) as $key => $item) {
                if (! is_string($key) && ! is_int($key)) continue;
                $result[$key] = self::normalizeFilterValue($item);
            }
            return $result;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
        return mb_substr(trim((string) $value), 0, 500);
    }

    /** Return a valid ISO calendar date or null when the input is invalid. */
    private static function isoDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return null;
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : null;
    }
}
