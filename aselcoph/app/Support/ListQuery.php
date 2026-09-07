<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Normalize list query params (search, filters, sort, pagination) for unified listings.
 */
class ListQuery
{
    /**
     * @param  list<string>  $filterKeys
     * @param  array<string, string>  $sortable  map of request key => db column
     * @return array{
     *     search: ?string,
     *     filters: array<string, mixed>,
     *     sort: ?string,
     *     dir: string,
     *     per_page: int,
     *     active_filter_count: int,
     *     query: array<string, mixed>
     * }
     */
    public static function from(
        Request $request,
        array $filterKeys = [],
        array $sortable = [],
        string $defaultSort = 'id',
        string $defaultDir = 'desc',
        int $defaultPerPage = 25,
        array $perPageOptions = [10, 25, 50, 100],
    ): array {
        $search = trim((string) $request->query('search', ''));
        $search = $search !== '' ? mb_substr($search, 0, 160) : null;

        $filters = [];
        foreach ($filterKeys as $key) {
            $value = $request->query($key);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }
            $filters[$key] = $value;
        }

        $sortKey = (string) $request->query('sort', $defaultSort);
        if ($sortable !== [] && ! array_key_exists($sortKey, $sortable)) {
            $sortKey = $defaultSort;
        }

        $dir = strtolower((string) $request->query('dir', $defaultDir));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        $perPage = (int) $request->query('per_page', $defaultPerPage);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = $defaultPerPage;
        }

        $query = array_filter([
            'search' => $search,
            ...$filters,
            'sort' => $sortKey !== $defaultSort || $request->has('sort') ? $sortKey : null,
            'dir' => ($sortKey !== $defaultSort || $request->has('sort') || $request->has('dir')) ? $dir : null,
            'per_page' => $perPage !== $defaultPerPage ? $perPage : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return [
            'search' => $search,
            'filters' => $filters,
            'sort' => $sortKey,
            'dir' => $dir,
            'per_page' => $perPage,
            'active_filter_count' => count($filters) + ($search ? 1 : 0),
            'query' => $query,
        ];
    }

    /**
     * @param  array<string, string>  $sortable
     */
    public static function applySort(Builder $builder, array $list, array $sortable): Builder
    {
        $column = $sortable[$list['sort']] ?? ($sortable[array_key_first($sortable)] ?? 'id');

        return $builder->orderBy($column, $list['dir'] === 'asc' ? 'asc' : 'desc');
    }

    /**
     * Build next sort URL for a column (asc → desc → clear to default).
     *
     * @param  array<string, mixed>  $currentQuery
     */
    public static function sortUrl(
        string $baseUrl,
        array $currentQuery,
        string $column,
        ?string $currentSort,
        string $currentDir,
        string $defaultSort = 'id',
    ): string {
        $query = $currentQuery;

        if ($currentSort === $column) {
            if ($currentDir === 'asc') {
                $query['sort'] = $column;
                $query['dir'] = 'desc';
            } else {
                unset($query['sort'], $query['dir']);
            }
        } else {
            $query['sort'] = $column;
            $query['dir'] = 'asc';
        }

        unset($query['page']);

        $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));

        return $baseUrl.($qs !== '' ? '?'.$qs : '');
    }

    /**
     * Reset URL keeping only path (no query).
     */
    public static function resetUrl(string $baseUrl): string
    {
        return $baseUrl;
    }
}
