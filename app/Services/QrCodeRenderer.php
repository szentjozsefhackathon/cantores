<?php

namespace App\Services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as an SVG fragment, ready to be written into a page.
 *
 * The same recipe Fortify engraves the two-factor code with, kept here because
 * this one encodes an ordinary URL rather than an `otpauth:` one. Black on
 * white and nothing else: this is read by a phone camera from across a room,
 * where contrast is the only thing that matters.
 *
 * @see \Laravel\Fortify\TwoFactorAuthenticatable::twoFactorQrCodeSvg()
 */
class QrCodeRenderer
{
    /**
     * The encoded data as an inline SVG, with the XML declaration removed so it
     * can sit inside an HTML document.
     */
    public function toSvg(string $data, int $size = 320): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1, null, null, Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(0, 0, 0),
                )),
                new SvgImageBackEnd,
            )
        ))->writeString($data);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
