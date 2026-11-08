<?php

namespace App\Domain\Farm\Shop;

/**
 * Why a shop page was not read: the address itself (scheme, private host, unresolved), the answer (too big, an HTTP
 * status, too many redirects, not HTML or JSON). `reason` is the code the admin's card translates, the message the detail.
 */
final class ShopRefused extends \RuntimeException
{
    public const SCHEME = 'scheme';

    public const PRIVATE_HOST = 'private_host';

    public const UNRESOLVED = 'unresolved';

    public const TOO_BIG = 'too_big';

    public const REDIRECTS = 'redirects';

    public const RATE_LIMITED = 'rate_limited';

    public const HTTP = 'http';

    public const UNREADABLE = 'unreadable';

    /** The link belongs to another colour of the catalogue already (the message names it). */
    public const TAKEN = 'taken';

    public function __construct(public readonly string $reason, string $message = '', public readonly int $status = 0)
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
