<?php

namespace App\Support;

/**
 * The <title> of a page as a search result shows it (docs/AP.md §15): about 60 characters fit on one line, the rest is
 * cut with "…". Every view hands the layout its words followed by the brand (" · matplace"); the brand stays only
 * while the whole fits. A long title keeps its own words instead: the name of the site stands above every result anyway.
 */
final class PageTitle
{
    public const BRAND = ' · matplace';

    public const MAX = 60;

    public static function fit(string $title): string
    {
        $title = trim($title);
        if (mb_strlen($title) > self::MAX && str_ends_with($title, self::BRAND) && mb_strlen($title) > mb_strlen(self::BRAND)) {
            return rtrim(mb_substr($title, 0, mb_strlen($title) - mb_strlen(self::BRAND)));
        }

        return $title;
    }

    /** A title with the brand while both fit ($words is the page's own part). */
    public static function of(string $words): string
    {
        return self::fit(trim($words).self::BRAND);
    }
}
