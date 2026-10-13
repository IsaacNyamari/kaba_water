<?php

namespace App\Services\Accounting\Exceptions;

/**
 * Thrown when payments of a written-off invoice would be added or removed.
 */
class InvoiceWrittenOffException extends AccountingException {}
