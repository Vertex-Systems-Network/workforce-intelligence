<?php

namespace Tests\Unit;

use App\Support\DataGridRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/** Verifies the database-safe server query contract used by DataGrid V2 endpoints. */
class DataGridRequestTest extends TestCase
{
    /** Normalize pagination, whitelisted sorting, scalar filters and date ranges. */
    public function test_normalizes_whitelisted_grid_query_state(): void
    {
        $request = Request::create('/api/v1/example', 'GET', [
            'page' => '3',
            'per_page' => '250',
            'search' => '  Alice  ',
            'sort' => '-created,name,-not_allowed',
            'filters' => [
                'status' => 'active',
                'created' => ['from' => '2026-08-01', 'to' => '2026-08-31'],
                'ignored' => 'should disappear',
            ],
        ]);

        $grid = DataGridRequest::from(
            $request,
            sortKeys: ['created', 'name'],
            filterKeys: ['status', 'created'],
            defaultSorting: [['id' => 'name', 'desc' => false]],
            maxPageSize: 100,
        );

        $this->assertSame(3, $grid->page);
        $this->assertSame(100, $grid->pageSize);
        $this->assertSame('Alice', $grid->search);
        $this->assertSame([
            ['id' => 'created', 'desc' => true],
            ['id' => 'name', 'desc' => false],
        ], $grid->sorting);
        $this->assertSame('active', $grid->filter('status'));
        $this->assertSame(['from' => '2026-08-01', 'to' => '2026-08-31'], $grid->dateRange('created'));
        $this->assertNull($grid->filter('ignored'));
    }

    /** Fall back to approved default sorting and discard invalid calendar dates. */
    public function test_uses_safe_defaults_and_validates_date_ranges(): void
    {
        $request = Request::create('/api/v1/example', 'GET', [
            'sort' => '-malicious',
            'filters' => ['created' => ['from' => '2026-02-31', 'to' => '2026-03-01']],
        ]);

        $grid = DataGridRequest::from(
            $request,
            sortKeys: ['name'],
            filterKeys: ['created'],
            defaultSorting: [['id' => 'name', 'desc' => false]],
        );

        $this->assertSame([['id' => 'name', 'desc' => false]], $grid->sorting);
        $this->assertSame(['to' => '2026-03-01'], $grid->dateRange('created'));
        $this->assertSame(1, $grid->page);
        $this->assertSame(25, $grid->pageSize);
    }

    /** Return stable pagination metadata for JSON list responses. */
    public function test_returns_standard_grid_meta(): void
    {
        $grid = DataGridRequest::from(Request::create('/api/v1/example', 'GET', ['page' => 2, 'per_page' => 25]), ['name']);
        $meta = $grid->meta(63);

        $this->assertSame(2, $meta['page']);
        $this->assertSame(25, $meta['per_page']);
        $this->assertSame(63, $meta['total']);
        $this->assertSame(3, $meta['last_page']);
    }
}
