<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The page exists, but not in the language of the address: a 404 that points to the Czech version. */
class UntranslatedPage extends NotFoundHttpException
{
    public function __construct(public readonly string $czechUrl)
    {
        parent::__construct('Not translated');
    }
}
