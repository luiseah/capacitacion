<?php

namespace Module\Pagination\Enums;

use Module\Pagination\PaginatedBuilder;

/**
 * The direction of a `sort[column]=desc` entry: validated with `Rule::enum()` by the list requests and
 * read by {@see PaginatedBuilder::sort()}.
 */
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
