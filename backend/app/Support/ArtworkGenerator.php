<?php

declare(strict_types=1);

namespace App\Support;

/**
 * يولّد لوحات تجريدية للبيانات التجريبية.
 *
 * السبب: منصة لبيع اللوحات تبدو مكسورة تماماً بصور بديلة رمادية. البذور
 * ثابتة لكل لوحة، فتُنتج الصورة نفسها في كل مرّة ويبقى العرض قابلاً
 * للإعادة. لا يُستخدم هذا الصنف في الإنتاج — انظر ImageService.
 */
final class ArtworkGenerator
{
    /** @var list<list<array{int,int,int}>> لوحات ألوان مستوحاة من أنماط فنية */
    private const PALETTES = [
        // تجريدي دافئ
        [[196, 168, 108], [176, 141, 87], [61, 56, 51], [250, 248, 245], [155, 74, 66]],
        // أزرق نيلي وذهب
        [[42, 58, 82], [74, 92, 107], [216, 192, 141], [247, 246, 244], [26, 23, 20]],
        // ترابي / صحراوي
        [[194, 156, 122], [168, 118, 62], [232, 213, 183], [92, 71, 54], [250, 248, 245]],
        // أخضر زيتي
        [[74, 107, 82], [110, 138, 106], [216, 224, 200], [45, 56, 44], [232, 229, 224]],
        // رمادي فحمي مع قرمزي
        [[41, 37, 33], [107, 98, 90], [232, 229, 224], [155, 74, 66], [196, 168, 108]],
    ];

    /**
     * @param  'flow'|'strata'|'fields'|'orbits'  $style
     * @return string محتوى PNG
     */
    public function generate(int $width, int $height, int $seed, string $style = 'flow'): string
    {
        mt_srand($seed);

        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);

        $palette = self::PALETTES[$seed % count(self::PALETTES)];

        $this->paintGround($image, $width, $height, $palette);

        match ($style) {
            'strata' => $this->paintStrata($image, $width, $height, $palette),
            'fields' => $this->paintFields($image, $width, $height, $palette),
            'orbits' => $this->paintOrbits($image, $width, $height, $palette),
            default => $this->paintFlow($image, $width, $height, $palette),
        };

        $this->addCanvasGrain($image, $width, $height);

        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();

        imagedestroy($image);
        mt_srand();

        return $contents;
    }

    /** تدرّج رأسي يملأ الخلفية. */
    private function paintGround($image, int $w, int $h, array $palette): void
    {
        [$top, $bottom] = [$palette[0], $palette[2]];

        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $color = imagecolorallocate(
                $image,
                (int) ($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) ($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) ($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($image, 0, $y, $w, $y, $color);
        }
    }

    /** أشرطة منحنية تتدفق عبر اللوحة. */
    private function paintFlow($image, int $w, int $h, array $palette): void
    {
        $bands = mt_rand(5, 9);

        for ($b = 0; $b < $bands; $b++) {
            $c = $palette[mt_rand(0, count($palette) - 1)];
            $color = imagecolorallocatealpha($image, $c[0], $c[1], $c[2], mt_rand(35, 85));

            $amplitude = mt_rand((int) ($h / 12), (int) ($h / 4));
            $offset = mt_rand(0, $h);
            $period = mt_rand((int) ($w / 3), $w);
            $thickness = mt_rand((int) ($h / 30), (int) ($h / 10));

            for ($x = 0; $x < $w; $x++) {
                $y = (int) ($offset + sin($x / max(1, $period) * M_PI * 2) * $amplitude);
                imagefilledrectangle($image, $x, $y, $x + 1, $y + $thickness, $color);
            }
        }
    }

    /** طبقات أفقية كطبقات صخرية. */
    private function paintStrata($image, int $w, int $h, array $palette): void
    {
        $y = 0;

        while ($y < $h) {
            $band = mt_rand((int) ($h / 24), (int) ($h / 7));
            $c = $palette[mt_rand(0, count($palette) - 1)];
            $color = imagecolorallocatealpha($image, $c[0], $c[1], $c[2], mt_rand(20, 70));

            $jitter = mt_rand((int) (-$h / 60), (int) ($h / 60));
            imagefilledrectangle($image, 0, $y + $jitter, $w, $y + $band, $color);

            $y += $band;
        }
    }

    /** مساحات لونية كبيرة متداخلة. */
    private function paintFields($image, int $w, int $h, array $palette): void
    {
        for ($i = 0, $n = mt_rand(4, 7); $i < $n; $i++) {
            $c = $palette[mt_rand(0, count($palette) - 1)];
            $color = imagecolorallocatealpha($image, $c[0], $c[1], $c[2], mt_rand(30, 75));

            $x1 = mt_rand((int) (-$w / 5), $w);
            $y1 = mt_rand((int) (-$h / 5), $h);

            imagefilledrectangle(
                $image,
                $x1,
                $y1,
                $x1 + mt_rand((int) ($w / 5), $w),
                $y1 + mt_rand((int) ($h / 6), (int) ($h / 2)),
                $color,
            );
        }
    }

    /** دوائر متحدة المركز. */
    private function paintOrbits($image, int $w, int $h, array $palette): void
    {
        $cx = mt_rand((int) ($w / 4), (int) ($w * 3 / 4));
        $cy = mt_rand((int) ($h / 4), (int) ($h * 3 / 4));

        for ($i = 0, $n = mt_rand(6, 12); $i < $n; $i++) {
            $c = $palette[mt_rand(0, count($palette) - 1)];
            $color = imagecolorallocatealpha($image, $c[0], $c[1], $c[2], mt_rand(40, 90));

            $r = (int) (min($w, $h) * (0.12 + $i * 0.07));
            imagefilledellipse($image, $cx, $cy, $r, $r, $color);
        }
    }

    /** حبيبات خفيفة تكسر نعومة التدرّج فتبدو الصورة كقماش لا كملف رقمي. */
    private function addCanvasGrain($image, int $w, int $h): void
    {
        $dots = (int) (($w * $h) / 220);

        for ($i = 0; $i < $dots; $i++) {
            $shade = mt_rand(0, 255);
            $color = imagecolorallocatealpha($image, $shade, $shade, $shade, mt_rand(100, 122));
            imagesetpixel($image, mt_rand(0, $w - 1), mt_rand(0, $h - 1), $color);
        }
    }
}
