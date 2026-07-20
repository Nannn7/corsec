<?php

namespace Modules\Corsec\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LogCorsecRequestErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);

            if ($response->getStatusCode() >= 400) {
                Log::warning('Corsec request returned error response', [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'query' => $request->query(),
                    'input' => $this->safeInput($request),
                    'user_id' => $request->user()?->id,
                    'status' => $response->getStatusCode(),
                    'response' => $this->responseSummary($response),
                ]);
            }

            return $response;
        } catch (Throwable $exception) {
            Log::error('Corsec request failed', [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'input' => $this->safeInput($request),
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $this->messageForUser($exception),
                ], $this->statusCode($exception));
            }

            throw $exception;
        }
    }

    private function safeInput(Request $request): array
    {
        $input = Arr::except($request->all(), [
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
        ]);

        if (count($input) > 30) {
            $input = array_slice($input, 0, 30, true);
            $input['_truncated'] = true;
        }

        return $input;
    }

    private function responseSummary(Response $response): ?array
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return null;
        }

        $content = (string) $response->getContent();
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        return Arr::only($decoded, ['success', 'message', 'errors']);
    }

    private function statusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        return 500;
    }

    private function messageForUser(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($exception instanceof AuthorizationException || $exception instanceof HttpExceptionInterface) {
            return $message !== '' ? $message : 'Aksi tidak dapat diproses.';
        }

        if (config('app.debug') && $message !== '') {
            return 'Aksi gagal diproses: ' . $message;
        }

        return 'Aksi gagal diproses. Detail teknis sudah dicatat di log.';
    }
}
