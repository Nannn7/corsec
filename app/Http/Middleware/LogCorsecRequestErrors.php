<?php

namespace Modules\Corsec\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
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

            throw $exception;
        }
    }
}
