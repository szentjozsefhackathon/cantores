<?php

namespace App\Services;

use App\Models\Author;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AuthorAvatarService
{
    const AVATAR_SIZE = 256;
    const THUMB_SIZE = 64;

    public function store(Author $author, UploadedFile $file, string $verticalAlign = 'top'): void
    {
        $imageData = file_get_contents($file->getRealPath());
        $source = imagecreatefromstring($imageData);

        if ($source === false) {
            throw new \RuntimeException('Could not create image from uploaded file.');
        }

        $large = $this->cropAndResize($source, self::AVATAR_SIZE, $verticalAlign);
        $thumb = $this->cropAndResize($source, self::THUMB_SIZE, $verticalAlign);

        imagedestroy($source);

        Storage::disk('public')->put(
            "authors/{$author->id}/avatar.jpg",
            $this->gdToJpeg($large)
        );
        Storage::disk('public')->put(
            "authors/{$author->id}/avatar_thumb.jpg",
            $this->gdToJpeg($thumb)
        );

        imagedestroy($large);
        imagedestroy($thumb);

        $author->update(['avatar' => "authors/{$author->id}"]);
    }

    public function delete(Author $author): void
    {
        Storage::disk('public')->delete("authors/{$author->id}/avatar.jpg");
        Storage::disk('public')->delete("authors/{$author->id}/avatar_thumb.jpg");
        $author->update(['avatar' => null]);
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
            default  => 0,
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
