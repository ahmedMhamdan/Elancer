<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PrepareProfilePhoto
{
    public function handle(UploadedFile $photo): string
    {
        $bytes = $photo->getContent();
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] * $size[1] > 16000000) {
            throw ValidationException::withMessages(['photo' => __('Choose a photo with no more than 16 million pixels.')]);
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => __('This image could not be opened. Try a different photo.')]);
        }
        $ratio = min(1, 512 / max($size[0], $size[1]));
        $width = max(1, (int) round($size[0] * $ratio));
        $height = max(1, (int) round($size[1] * $ratio));
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw ValidationException::withMessages(['photo' => __('We could not save your photo. Please try again.')]);
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
            throw ValidationException::withMessages(['photo' => __('We could not save your photo. Please try again.')]);
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
            // Only sanitized profile-photo bytes are shared. No public URL or identity documents.
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
            throw ValidationException::withMessages(['photo' => __('This photo did not pass the workplace-safe content check. Choose another photo.')]);
        }
        if ($response->json('summary.action') !== 'accept') {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['photo' => __('Photo safety checks are unavailable. Your current photo has not changed. Please try again later.')]);
    }
}
