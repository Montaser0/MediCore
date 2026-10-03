<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when an operation is well-formed but violates a business rule,
 * e.g. dispensing more stock than a batch holds.
 */
class BusinessRuleException extends DomainException
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    protected string $errorCode = 'business_rule_violation';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function make(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}
