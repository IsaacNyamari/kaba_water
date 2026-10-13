<?php

namespace App\Services\Accounting\Exceptions;

use RuntimeException;

/** Base class so callers can catch every ledger rule violation at once. */
class AccountingException extends RuntimeException {}
