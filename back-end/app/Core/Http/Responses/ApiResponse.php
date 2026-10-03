<?php

declare(strict_types=1);

namespace App\Core\Http\Responses;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single place where the HTTP envelope of the API is decided, so the Next.js
 * client can rely on one shape for every endpoint.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, array $meta = [], int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    public static function resource(JsonResource $resource, int $status = Response::HTTP_OK): JsonResponse
    {
        return $resource->response()->setStatusCode($status);
    }

    public static function created(JsonResource $resource): JsonResponse
    {
        return self::resource($resource, Response::HTTP_CREATED);
    }

    public static function collection(ResourceCollection $collection): JsonResponse
    {
        return $collection->response();
    }

    /**
     * Normalises Laravel pagination into the `data` + `meta` shape used by the
     * frontend data tables.
     */
    public static function paginated(ResourceCollection $collection, Paginator $paginator): JsonResponse
    {
        return self::data($collection->resolve(), [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
            'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
        ]);
    }

    public static function message(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
