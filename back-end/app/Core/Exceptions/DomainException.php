<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for every error raised by domain logic rather than by
 * infrastructure. Rendering is handled here so that modules never need to
 * know about HTTP.
 */
abstract class DomainException extends Exception
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    protected string $errorCode = 'domain_error';

    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message = '', array $context = [])
    {
        parent::__construct($message !== '' ? $message : static::class);

        $this->context = $context;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => $this->errorCode(),
            'context' => $this->context(),
        ], $this->status());
    }
}
