<?php

class BusinessException extends RuntimeException
{
    private int $errorCode;
    private array $context;

    public function __construct(int $errorCode, string $message = '', array $context = [])
    {
        $this->errorCode = $errorCode;
        $this->context = $context;
        $actualMessage = $message ?: ErrorCode::getMessage($errorCode);
        parent::__construct($actualMessage);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getHttpStatusCode(): int
    {
        if ($this->errorCode >= 5000 && $this->errorCode < 6000) {
            return 404;
        }
        if ($this->errorCode >= 3000 && $this->errorCode < 4000) {
            return 401;
        }
        if ($this->errorCode >= 4000 && $this->errorCode < 5000) {
            return 403;
        }
        if ($this->errorCode >= 2000 && $this->errorCode < 3000) {
            return 400;
        }
        if ($this->errorCode >= 1000 && $this->errorCode < 2000) {
            return 500;
        }
        if ($this->errorCode >= 7000 && $this->errorCode < 8000) {
            return 500;
        }
        if ($this->errorCode >= 8000 && $this->errorCode < 9000) {
            return 502;
        }
        return 200;
    }
}
