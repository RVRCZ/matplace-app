<?php

namespace App\Events;

use App\Models\User;

/**
 * An account is being deleted (App\Domain\Account\AccountEraser). Fired inside the transaction, while the user
 * still has their data: listeners hide or remove what their feature keeps about the person.
 */
class AccountErasing
{
    public function __construct(public readonly User $user) {}
}
