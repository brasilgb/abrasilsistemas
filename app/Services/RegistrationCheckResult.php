<?php

namespace App\Services;

use Carbon\CarbonInterface;

/** Resultado de uma verificação de cadastro (CRM-WA-29): registered, not_registered ou error. */
final readonly class RegistrationCheckResult
{
    private function __construct(
        public string $status,
        public ?CarbonInterface $registeredAt = null,
        public ?string $error = null,
        public ?int $httpStatus = null,
        public bool $trialStarted = false,
    ) {}

    public static function registered(CarbonInterface $registeredAt): self
    {
        return new self('registered', registeredAt: $registeredAt);
    }

    public static function notRegistered(): self
    {
        return new self('not_registered');
    }

    public static function error(string $error, ?int $httpStatus = null): self
    {
        return new self('error', error: $error, httpStatus: $httpStatus);
    }

    public function withTrialStarted(bool $started): self
    {
        return new self($this->status, $this->registeredAt, $this->error, $this->httpStatus, $started);
    }

    public function isRegistered(): bool
    {
        return $this->status === 'registered';
    }
}
