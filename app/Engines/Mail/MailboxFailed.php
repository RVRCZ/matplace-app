<?php

namespace App\Engines\Mail;

use App\Engines\Exceptions\EngineException;

/** The mailbox did not answer, or refused: the message goes to the admin. */
final class MailboxFailed extends EngineException {}
