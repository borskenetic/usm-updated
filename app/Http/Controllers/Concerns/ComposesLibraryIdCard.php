<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PublicAssetPath;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * USM student/employee ID card layout (templates: 556×874).
 */
trait ComposesLibraryIdCard
{
    protected function idCardTemplate(string $side)
    {
        $path = PublicAssetPath::resolve("images/id_templates/{$side}.png")
            ?? base_path("images/id_templates/{$side}.png");

        return Image::make($path);
    }

    protected function idCardFont(bool $bold = true): string
    {
        $candidates = $bold
            ? [
                public_path('fonts/arialbd.ttf'),
                public_path('fonts/Arial Bold.ttf'),
                'C:/Windows/Fonts/arialbd.ttf',
                'C:/Windows/Fonts/ARIALBD.TTF',
            ]
            : [
                public_path('fonts/arial.ttf'),
                public_path('fonts/Arial.ttf'),
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/ARIAL.TTF',
            ];

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return public_path('fonts/arial.ttf');
    }

    protected function drawIdCardText(
        $img,
        string $text,
        int $x,
        int $y,
        int $size,
        string $color = '#000',
        string $align = 'center',
        string $valign = 'top',
        bool $bold = true
    ): void {
        $fontPath = $this->idCardFont($bold);

        if ($bold && ! file_exists($fontPath)) {
            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$ox, $oy]) {
                $img->text($text, $x + $ox, $y + $oy, function ($font) use ($fontPath, $size, $color, $align, $valign) {
                    $font->file($fontPath);
                    $font->size($size);
                    $font->color($color);
                    $font->align($align);
                    $font->valign($valign);
                });
            }
        }

        $img->text($text, $x, $y, function ($font) use ($fontPath, $size, $color, $align, $valign) {
            $font->file($fontPath);
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign($valign);
        });
    }

    /**
     * Build a display name: FIRSTNAME M. LASTNAME (uppercased).
     */
    protected function formatIdCardName(?string $firstname, ?string $lastname, ?string $middleInitial = null): string
    {
        $first = trim((string) $firstname);
        $last = trim((string) $lastname);
        $mi = trim((string) $middleInitial);

        if ($mi !== '') {
            $mi = rtrim($mi, '.').'.';
            $name = trim("{$first} {$mi} {$last}");
        } else {
            $name = trim("{$first} {$last}");
        }

        return mb_strtoupper($name);
    }

    /**
     * @param  array{
     *     photo:?string,
     *     full_name:string,
     *     subtitle:?string,
     *     id_number:?string
     * }  $data
     */
    protected function composeIdCardFront($img, array $data)
    {
        // Yellow photo placeholder on template: x=254–527, y=52–370 (274×319)
        $photoPath = PublicAssetPath::resolve($data['photo'] ?? null);
        if ($photoPath) {
            $profile = Image::make($photoPath)->fit(274, 319);
            $img->insert($profile, 'top-left', 254, 52);
        }

        // Green ID number under university seal (left of photo)
        if (! empty($data['id_number'])) {
            $this->drawIdCardText($img, trim($data['id_number']), 148, 340, 17, '#00A651', 'center', 'top', true);
        }

        // Name + program/department in yellow lower band (above director signature)
        if (! empty($data['full_name'])) {
            $this->drawIdCardText($img, $data['full_name'], 278, 652, 17, '#000000', 'center', 'top', true);
        }

        if (! empty($data['subtitle'])) {
            $this->drawIdCardText($img, trim($data['subtitle']), 278, 682, 15, '#000000', 'center', 'top', true);
        }

        return $img;
    }

    /**
     * @param  array{
     *     qrcode:string,
     *     signature:?string,
     *     emergency_person:?string,
     *     emergency_address:?string,
     *     emergency_number:?string,
     *     birth_date:?string,
     *     valid_until:?string
     * }  $data
     */
    protected function composeIdCardBack($img, array $data)
    {
        // Emergency contact under top black header (y ~60–175)
        $ey = 80;
        if (! empty($data['emergency_person'])) {
            $this->drawIdCardText($img, $data['emergency_person'], 278, $ey, 15, '#000', 'center', 'top', true);
            $ey += 27;
        }
        if (! empty($data['emergency_address'])) {
            $this->drawIdCardText(
                $img,
                $data['emergency_address'],
                278,
                $ey,
                10,
                '#000',
                'center',
                'top',
                false
            );
            $ey += 25;
        }
        if (! empty($data['emergency_number'])) {
            $this->drawIdCardText($img, $data['emergency_number'], 278, $ey, 14, '#000', 'center', 'top', true);
        }

        // After baked "*Date of Birth:*" label (ends ~x=187)
        if (! empty($data['birth_date'])) {
            $formattedDate = Carbon::parse($data['birth_date'])->format('F j, Y');
            $this->drawIdCardText($img, $formattedDate, 195, 198, 12, '#000', 'left', 'top', true);
        }

        // QR code lower-left (next to student's signature line)
        $qrPng = QrCode::format('png')
            ->size(160)
            ->margin(0)
            ->generate($data['qrcode']);
        $qrImage = Image::make((string) $qrPng);
        $img->insert($qrImage, 'top-left', 40, 490);

        // Signature above the right-side "Student's Signature" line (y≈638, x=245–508)
        $signaturePath = PublicAssetPath::resolve($data['signature'] ?? null);
        if ($signaturePath) {
            $signature = Image::make($signaturePath)->resize(180, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $img->insert($signature, 'top-left', 320, 520);
        }

        // Below "*Valid until:*" black bar (y≈720–785)
        if (! empty($data['valid_until'])) {
            $this->drawIdCardText($img, $data['valid_until'], 278, 820, 15, '#000', 'center', 'top', true);
        }

        return $img;
    }
}
