<?php

namespace App\Services;

use App\Models\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CollectionCoverService
{
    const COVER_SIZE = 256;

    const THUMB_SIZE = 64;

    public function store(Collection $collection, UploadedFile $file, string $verticalAlign = 'top'): void
    {
        $imageData = file_get_contents($file->getRealPath());
        $source = imagecreatefromstring($imageData);

        if ($source === false) {
            throw new \RuntimeException('Could not create image from uploaded file.');
        }

        $hash = substr(md5($imageData), 0, 8);

        $large = $this->cropAndResize($source, self::COVER_SIZE, $verticalAlign);
        $thumb = $this->cropAndResize($source, self::THUMB_SIZE, $verticalAlign);

        imagedestroy($source);

        $this->deleteFiles($collection);

        Storage::disk('public')->put(
            "collections/{$collection->id}/cover_{$hash}.jpg",
            $this->gdToJpeg($large)
        );
        Storage::disk('public')->put(
            "collections/{$collection->id}/cover_thumb_{$hash}.jpg",
            $this->gdToJpeg($thumb)
        );

        imagedestroy($large);
        imagedestroy($thumb);

        $collection->update(['cover' => $hash]);
    }

    public function delete(Collection $collection): void
    {
        $this->deleteFiles($collection);
        $collection->update(['cover' => null]);
    }

    private function deleteFiles(Collection $collection): void
    {
        if (! $collection->cover) {
            return;
        }

        if (str_contains($collection->cover, '/')) {
            // Legacy format: cover stored as "collections/{id}"
            Storage::disk('public')->delete("collections/{$collection->id}/cover.jpg");
            Storage::disk('public')->delete("collections/{$collection->id}/cover_thumb.jpg");
        } else {
            $hash = $collection->cover;
            Storage::disk('public')->delete("collections/{$collection->id}/cover_{$hash}.jpg");
            Storage::disk('public')->delete("collections/{$collection->id}/cover_thumb_{$hash}.jpg");
        }
    }

    private function cropAndResize(\GdImage $source, int $size, string $verticalAlign = 'top'): \GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $srcMin = min($srcW, $srcH);
        $srcX = intval(($srcW - $srcMin) / 2);
        $srcY = match ($verticalAlign) {
            'center' => intval(($srcH - $srcMin) / 2),
            'bottom' => $srcH - $srcMin,
            default => 0,
        };

        $dest = imagecreatetruecolor($size, $size);
        imagecopyresampled($dest, $source, 0, 0, $srcX, $srcY, $size, $size, $srcMin, $srcMin);

        return $dest;
    }

    private function gdToJpeg(\GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 90);

        return ob_get_clean();
    }
}
