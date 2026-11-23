<?php

namespace Soara\Debugger\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * The result of an action, shown as a notice at the top of the page.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function notice(string $text, string $tone = 'success', array $extra = []): JsonResponse
    {
        return response()->json(['notice' => ['tone' => $tone, 'text' => $text], ...$extra]);
    }

    /**
     * Previous / next page links for list endpoints.
     *
     * @return array{page: int, range: string, has_previous: bool, has_next: bool, last_page: ?int}
     */
    protected function pages(int $page, int $count, int $perPage, bool $hasNext, ?int $total = null): array
    {
        $from = ($page - 1) * $perPage + 1;

        return [
            'page' => $page,
            'last_page' => $total !== null ? max(1, (int) ceil($total / $perPage)) : null,
            'range' => $count === 0 ? '' : $from.'–'.($from + $count - 1).($total !== null ? ' of '.number_format($total) : ''),
            'has_previous' => $page > 1,
            'has_next' => $hasNext,
        ];
    }
}
