<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PwaController extends Controller
{
    public function manifest(SettingsRepository $settings): JsonResponse
    {
        $icon = $settings->get('pwa_app_icon');
        $icons = $icon
            ? collect([192, 512])->map(fn (int $size): array => ['src' => route('pwa.icon', $size).'?v='.$this->iconVersion((string) $icon), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any maskable'])->all()
            : [['src' => asset('icons/app-icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']];

        return response()->json(['id' => '/', 'name' => $settings->get('pwa_app_name', config('app.name')), 'short_name' => $settings->get('pwa_short_name', 'Agency'), 'start_url' => '/', 'scope' => '/', 'display' => 'standalone', 'orientation' => 'any', 'theme_color' => $settings->get('pwa_theme_color', '#435cea'), 'background_color' => $settings->get('pwa_background_color', '#e6e9fc'), 'icons' => $icons, 'related_applications' => [['platform' => 'webapp', 'url' => route('pwa.manifest')]], 'prefer_related_applications' => false])->header('Content-Type', 'application/manifest+json')->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function icon(int $size, SettingsRepository $settings): Response
    {
        abort_unless(in_array($size, [64, 180, 192, 512], true), 404);
        $path = $settings->get('pwa_app_icon');
        $absolutePath = $path ? storage_path('app/public/'.$path) : null;
        abort_unless($absolutePath && is_file($absolutePath), 404);

        $source = imagecreatefromstring((string) file_get_contents($absolutePath));
        abort_unless($source !== false, 422, 'The configured PWA icon is not a supported image.');
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropSize = min($sourceWidth, $sourceHeight);
        $destination = imagecreatetruecolor($size, $size);
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        imagecopyresampled($destination, $source, 0, 0, (int) (($sourceWidth - $cropSize) / 2), (int) (($sourceHeight - $cropSize) / 2), $size, $size, $cropSize, $cropSize);
        ob_start();
        imagepng($destination, null, 9);
        $contents = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($destination);

        return response($contents, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function iconVersion(string $path): string
    {
        $absolutePath = storage_path('app/public/'.$path);

        return sha1($path.(is_file($absolutePath) ? (string) filemtime($absolutePath) : 'missing'));
    }
}
