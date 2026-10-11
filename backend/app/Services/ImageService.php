<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * تخزين الصور وتوليد نسخها.
 *
 * كل ما يُخزَّن يمرّ من هنا، ولذلك سببان:
 *
 * ١) الصور هي المنتج نفسه في هذه المنصة. تحويلها إلى WebP يقلّص الحجم إلى
 *    نحو ثلث JPEG بالجودة نفسها، والفرق يظهر مباشرةً في زمن ظهور المعرض.
 *
 * ٢) التخزين يمرّ عبر قرص Laravel لا عبر مسارات مباشرة، فالانتقال إلى S3
 *    أو CDN لاحقاً يكون بتغيير FILESYSTEM_DISK دون لمس أي صنف.
 */
final class ImageService
{
    /**
     * يحفظ صورة ويولّد نسخها.
     *
     * @return array{path: string, variants: array<string, string>}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        return $this->storeContents($file->get(), $directory);
    }

    /**
     * @return array{path: string, variants: array<string, string>}
     */
    public function storeContents(string $contents, string $directory): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        $format = (string) config('platform.images.format', 'webp');
        $quality = (int) config('platform.images.quality', 82);

        $name = Str::uuid()->toString();
        $original = "{$directory}/{$name}.{$format}";

        $image = Image::decodeBinary($contents);
        $disk->put($original, (string) $image->encodeUsingFileExtension($format, quality: $quality));

        $variants = [];

        foreach ((array) config('platform.images.variants', []) as $variant => $width) {
            // لا نُكبّر صورة أصغر من المقاس المطلوب: التكبير يضيف بايتات
            // ولا يضيف تفصيلاً، والنتيجة أسوأ من الأصل.
            if ($image->width() <= (int) $width) {
                $variants[$variant] = $original;

                continue;
            }

            $path = "{$directory}/{$name}_{$variant}.{$format}";

            $resized = Image::decodeBinary($contents)->scaleDown(width: (int) $width);
            $disk->put($path, (string) $resized->encodeUsingFileExtension($format, quality: $quality));

            $variants[$variant] = $path;
        }

        return ['path' => $original, 'variants' => $variants];
    }

    /** يحذف الأصل وكل نسخه. */
    public function delete(?string $path, ?array $variants = null): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        foreach (array_unique(array_filter([$path, ...array_values($variants ?? [])])) as $target) {
            $disk->delete($target);
        }
    }
}
