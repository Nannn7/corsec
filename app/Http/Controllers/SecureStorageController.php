<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecureStorageController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $path = $this->normalizePath($path);

        Log::info('storage.secure.request', [
            'path' => $path,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            Log::warning('storage.secure.not_found', [
                'path' => $path,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            abort(404, 'File tidak ditemukan.');
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $filename = $this->safeFilename(basename($path));

        Log::info('storage.secure.serve', [
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'user_id' => $request->user()?->id,
        ]);

        return response()->file($disk->path($path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                Log::warning('storage.secure.blocked', [
                    'path' => $path,
                    'reason' => 'path_traversal',
                ]);

                abort(403, 'Akses file tidak valid.');
            }
        }

        return $path;
    }

    private function safeFilename(string $filename): string
    {
        $filename = str_replace(['\\', '/', '"', "\r", "\n"], '', $filename);

        return $filename !== '' ? $filename : 'file';
    }
}
