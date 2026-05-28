<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MoodleApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $moodleException = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $debugInfo = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
