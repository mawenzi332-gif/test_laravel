<?php

namespace App\OpenApi;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;

class ApiErrorResponses extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $path = $routeInfo->route->uri();

        if (! str_starts_with($path, 'api/v1/')) {
            return;
        }

        $this->correctFortifySuccessResponse($operation, $path);

        $existingErrors = [];
        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            function ($response) use (&$existingErrors): bool {
                if ($response instanceof Reference) {
                    $response = $response->resolve();
                }

                if (! $response instanceof Response || ! is_numeric($response->code) || (int) $response->code < 400) {
                    return true;
                }

                $existingErrors[] = (int) $response->code;

                return false;
            }
        ));

        $statuses = array_unique([
            ...$this->statusesFor($path, $routeInfo->method),
            ...$existingErrors,
        ]);

        foreach ($statuses as $status) {
            $operation->addResponse($this->response($status));
        }
    }

    private function correctFortifySuccessResponse(Operation $operation, string $path): void
    {
        $successStatus = match ($path) {
            'api/v1/auth/register' => 201,
            'api/v1/auth/logout' => 204,
            'api/v1/auth/forgot-password', 'api/v1/auth/reset-password' => 200,
            default => null,
        };

        if ($successStatus === null) {
            return;
        }

        if (in_array($path, ['api/v1/auth/forgot-password', 'api/v1/auth/reset-password'], true)) {
            $operation->responses = array_values(array_filter(
                $operation->responses ?? [],
                function ($response): bool {
                    if ($response instanceof Reference) {
                        $response = $response->resolve();
                    }

                    return ! ($response instanceof Response && is_numeric($response->code) && (int) $response->code === 200);
                }
            ));

            $message = (new OpenApiTypes\ObjectType)
                ->addProperty('message', new OpenApiTypes\StringType)
                ->setRequired(['message']);

            $operation->addResponse(Response::make(200)
                ->setDescription('Operation completed')
                ->setContent('application/json', Schema::fromType($message)));

            return;
        }

        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            function ($response): bool {
                if ($response instanceof Reference) {
                    $response = $response->resolve();
                }

                return ! ($response instanceof Response && is_numeric($response->code) && (int) $response->code < 400);
            }
        ));

        $response = Response::make($successStatus)
            ->setDescription($successStatus === 204 ? 'Session ended' : 'Account created');

        if ($successStatus === 201) {
            $response->setContent('application/json', Schema::fromType(new OpenApiTypes\StringType));
        }

        $operation->addResponse($response);
    }

    /** @return list<int> */
    private function statusesFor(string $path, string $method): array
    {
        $statuses = [500];

        if (str_contains($path, '/auth/')) {
            $statuses[] = 422;

            if (in_array($path, [
                'api/v1/auth/login',
                'api/v1/auth/register',
                'api/v1/auth/forgot-password',
                'api/v1/auth/reset-password',
            ], true)) {
                $statuses[] = 429;
            }

            if ($path === 'api/v1/auth/logout') {
                $statuses[] = 401;
            }
        }

        if ($path === 'api/v1/comments') {
            $statuses[] = 422;
            if ($method === 'POST') {
                $statuses[] = 429;
            }
        }

        if (in_array($path, ['api/v1/comments/preview', 'api/v1/me'], true)) {
            if ($method === 'POST' || $method === 'PATCH') {
                $statuses[] = 422;
            }
            if ($path === 'api/v1/me') {
                $statuses[] = 401;
            }
        }

        if (str_starts_with($path, 'api/v1/comments/{comment}')) {
            $statuses = [...$statuses, 401, 403, 404];
            if ($method === 'PATCH') {
                $statuses[] = 422;
            }
        }

        if (str_starts_with($path, 'api/v1/attachments/')) {
            $statuses[] = 404;
        }

        return array_values(array_unique($statuses));
    }

    private function response(int $status): Response
    {
        $code = (new OpenApiTypes\StringType)->enum([
            'VALIDATION_ERROR',
            'CAPTCHA_FAILED',
            'AUTHENTICATION_FAILED',
            'UNAUTHENTICATED',
            'FORBIDDEN',
            'NOT_FOUND',
            'INVALID_FILE',
            'FILE_TOO_LARGE',
            'RATE_LIMITED',
            'EXTERNAL_SERVICE_ERROR',
            'INTERNAL_ERROR',
        ]);

        $error = (new OpenApiTypes\ObjectType)
            ->addProperty('code', $code)
            ->addProperty('message', new OpenApiTypes\StringType)
            ->addProperty(
                'details',
                (new OpenApiTypes\ObjectType)->additionalProperties(
                    (new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType)
                )
            )
            ->setRequired(['code', 'message', 'details']);

        $body = (new OpenApiTypes\ObjectType)
            ->addProperty('error', $error)
            ->setRequired(['error']);

        return Response::make($status)
            ->setDescription(match ($status) {
                401 => 'Authentication required',
                403 => 'Forbidden',
                404 => 'Resource not found',
                422 => 'Request validation failed',
                429 => 'Rate limit exceeded',
                default => 'Unexpected server error',
            })
            ->setContent('application/json', Schema::fromType($body));
    }
}
