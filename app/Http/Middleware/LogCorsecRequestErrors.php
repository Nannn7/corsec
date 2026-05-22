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
            return $next($request);
        } catch (Throwable $exception) {
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

            Log::error('Corsec request failed', [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'input' => $input,
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
