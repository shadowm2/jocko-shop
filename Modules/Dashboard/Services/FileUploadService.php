<?php

namespace Modules\Dashboard\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Dashboard\Models\Media;

class FileUploadService
{
    public function upload(
        UploadedFile $file,
        string $module,
        Model $model,
        ?string $disk = null,
        ?string $collection = 'images',
        ?int $order = 0,
        ?string $customDir = null

    ): Media {
        $module = Str::of($module)->lower()->toString();
        $baseClassName = Str::of(class_basename($model))->lower()->plural()->toString();
        $mediaService = resolve(MediaService::class);
        $disk ??= 'public';
        $directory = "{$module}".DIRECTORY_SEPARATOR.$baseClassName;
        if (! empty($customDir)) {
            $directory .= DIRECTORY_SEPARATOR.$customDir;
        }

        $mediaData = [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'collection' => $collection,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'order' => $order,
        ];
        $filePath = $file->store($directory, $disk);

        return $mediaService->create([
            ...$mediaData,
            'path' => $filePath,
        ]);
    }

    public function delete(
        ?string $path,
        string $disk = 'public'
    ): void {
        if ($path && \Storage::disk($disk)->exists($path)) {
            \Storage::disk($disk)->delete($path);
        }
    }
}
