<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PrepareProfilePhoto
{
    /** The field the member's messages are reported on; portfolio images use their own wording. */
    private string $field = 'photo';

    /** Portfolio case images pass the same normalization and content check at a larger size. */
    public function image(UploadedFile $image): string
    {
        $this->field = 'image';

        return $this->handle($image, 1600);
    }

    public function handle(UploadedFile $photo, int $side = 512): string
    {
        $bytes = $photo->getContent();
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] * $size[1] > 16000000) {
            $this->fail(__('Choose a photo with no more than 16 million pixels.'), __('Choose an image with no more than 16 million pixels.'));
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            $this->fail(__('This image could not be opened. Try a different photo.'), __('This image could not be opened. Try a different image.'));
        }
        $ratio = min(1, $side / max($size[0], $size[1]));
        $width = max(1, (int) round($size[0] * $ratio));
        $height = max(1, (int) round($size[1] * $ratio));
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            $this->unsaved();
        }
        // The destination is a true-color image; flatten transparency onto white.
        imagefill($image, 0, 0, 0xFFFFFF);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start();
        try {
            imagejpeg($image, null, 88);
            $normalized = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($image);
        }
        if (! is_string($normalized) || $normalized === '') {
            $this->unsaved();
        }
        $this->moderate($normalized);

        return $normalized;
    }

    private function moderate(string $bytes): void
    {
        $user = config('services.sightengine.user');
        $secret = config('services.sightengine.secret');
        $workflow = config('services.sightengine.workflow');
        if (! $user || ! $secret || ! $workflow) {
            $this->unavailable();
        }
        try {
            // Only sanitized photo or portfolio-image bytes are shared. No public URL or identity documents.
            $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->attach('media', $bytes, 'profile.jpg', ['Content-Type' => 'image/jpeg'])
                ->post('https://api.sightengine.com/1.0/check-workflow.json', [
                    'api_user' => $user, 'api_secret' => $secret, 'workflow' => $workflow,
                ]);
        } catch (ConnectionException) {
            $this->unavailable();
        }
        if (! $response->successful() || $response->json('status') !== 'success'
            || $response->json('workflow.id') !== $workflow) {
            $this->unavailable();
        }
        if ($response->json('summary.action') === 'reject') {
            $this->fail(__('This photo did not pass the workplace-safe content check. Choose another photo.'), __('This image did not pass the workplace-safe content check. Choose another image.'));
        }
        if ($response->json('summary.action') !== 'accept') {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        $this->fail(__('Photo safety checks are unavailable. Your current photo has not changed. Please try again later.'), __('Image safety checks are unavailable. The image was not added. Please try again later.'));
    }

    private function unsaved(): never
    {
        $this->fail(__('We could not save your photo. Please try again.'), __('We could not save this image. Please try again.'));
    }

    private function fail(string $photo, string $image): never
    {
        throw ValidationException::withMessages([$this->field => $this->field === 'photo' ? $photo : $image]);
    }
}
