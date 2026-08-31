<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Corsec\Models\Attachment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecureStorageController extends Controller
{
    public function inlineAttachment(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $path = $this->normalizePath((string) $attachment->path);
        $diskName = (string) ($attachment->disk ?: 'private');
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            Log::warning('attachment.inline.not_found', [
                'attachment_id' => $attachment->id,
                'disk' => $diskName,
                'path' => $path,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            abort(404, 'File tidak ditemukan.');
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $filename = $this->safeFilename((string) ($attachment->original_name ?: $attachment->file_name ?: basename($path)));

        Log::info('attachment.inline.serve', [
            'attachment_id' => $attachment->id,
            'disk' => $diskName,
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

    public function viewAttachment(Request $request, Attachment $attachment)
    {
        $path = $this->normalizePath((string) $attachment->path);
        $diskName = (string) ($attachment->disk ?: 'private');
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fileName = $this->safeFilename((string) ($attachment->original_name ?: $attachment->file_name ?: basename($path)));
        $inlineUrl = route('attachment.inline', $attachment);
        $downloadUrl = route('attachment.download', $attachment);

        return view('corsec::attachment.viewer', compact('attachment', 'fileName', 'inlineUrl', 'downloadUrl'));
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $path = $this->normalizePath((string) $attachment->path);
        $diskName = (string) ($attachment->disk ?: 'private');
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fileName = $this->safeFilename((string) ($attachment->original_name ?: $attachment->file_name ?: basename($path)));

        return $disk->download($path, $fileName);
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
