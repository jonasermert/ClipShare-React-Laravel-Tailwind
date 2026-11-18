<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $videos = Video::where('user_id', $request->user()->id)
            ->latest()->orderBy('id')->get()
            ->map(fn (Video $video) => $this->view($video, $request));

        return response()->json(['videos' => $videos]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->metadata($request);
        $file = $request->file('video');
        abort_if(
            $file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_INI_SIZE,
            413,
            'Das Video überschreitet das PHP-Upload-Limit.'
        );
        $request->validate(['video' => ['required', 'file']]);

        abort_if($file->getSize() > 500 * 1024 * 1024, 413, 'Das Video darf höchstens 500 MiB groß sein.');

        $mimeType = $file->getMimeType();
        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'video/')) {
            throw ValidationException::withMessages([
                'video' => 'Bitte wähle eine gültige Videodatei aus.',
            ]);
        }

        $filename = Str::uuid().'.'.($file->guessExtension() ?? 'video');
        $disk = Storage::disk('uploads');

        if ($disk->putFileAs('', $file, $filename) === false) {
            throw new RuntimeException('Die Videodatei konnte nicht gespeichert werden.');
        }

        try {
            $video = Video::create([
                ...$data,
                'id' => (string) Str::ulid(),
                'user_id' => $request->user()->id,
                'filename' => $filename,
                'mime_type' => $mimeType,
            ]);
        } catch (Throwable $exception) {
            $disk->delete($filename);
            throw $exception;
        }

        return response()->json(['video' => $this->view($video, $request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['video' => $this->view($this->ownedVideo($request, $id), $request)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $video = $this->ownedVideo($request, $id);
        $video->update($this->metadata($request));

        return response()->json(['video' => $this->view($video, $request)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $video = $this->ownedVideo($request, $id);
        $video->delete();

        $disk = Storage::disk('uploads');
        if (! $disk->delete($video->filename)) {
            report(new RuntimeException('Eine gelöschte Videodatei konnte nicht vom Datenträger entfernt werden.'));
        }

        return response()->json(['ok' => true]);
    }

    public function shared(Request $request, string $id): JsonResponse
    {
        $video = Video::find($id);
        abort_if($video === null, 404, 'Video nicht gefunden.');

        return response()->json(['video' => $this->view($video, $request)]);
    }

    public function media(string $filename): BinaryFileResponse
    {
        $video = Video::where('filename', $filename)->first();
        $disk = Storage::disk('uploads');
        abort_if($video === null || ! $disk->exists($filename), 404, 'Video nicht gefunden.');

        $detectedMime = $disk->mimeType($filename);
        $mimeType = is_string($detectedMime) && str_starts_with($detectedMime, 'video/')
            ? $detectedMime
            : 'application/octet-stream';

        return response()->file($disk->path($filename), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    private function ownedVideo(Request $request, string $id): Video
    {
        $video = Video::where('user_id', $request->user()->id)->find($id);
        abort_if($video === null, 404, 'Video nicht gefunden.');

        return $video;
    }

    private function metadata(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:16000'],
        ]);

        return ['title' => $data['title'], 'description' => $data['description'] ?? ''];
    }

    private function view(Video $video, Request $request): array
    {
        $origin = app()->isProduction() ? config('app.url') : $request->getSchemeAndHttpHost();

        return [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'createdAt' => $video->created_at->toISOString(),
            'updatedAt' => $video->updated_at->toISOString(),
            'videoUrl' => '/uploads/'.rawurlencode($video->filename),
            'shareUrl' => rtrim($origin, '/').'/view/'.rawurlencode($video->id),
        ];
    }
}
