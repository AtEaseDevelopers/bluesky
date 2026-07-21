<?php

namespace App\Services\RevenueMonster\Exceptions;

use RuntimeException;
use Throwable;

class RevenueMonsterException extends RuntimeException
{
    /** Revenue Monster error code (string) when the failure came from the API envelope. */
    protected ?string $rmErrorCode;

    public function __construct(string $message = '', ?string $rmErrorCode = null, int $httpStatus = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);

        $this->rmErrorCode = $rmErrorCode;
    }

    public function getRmErrorCode(): ?string
    {
        return $this->rmErrorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
