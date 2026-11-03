<?php

namespace Modules\Order\Payments;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as inline SVG, so a customer can scan the payment page with their
 * phone instead of someone typing or sending the link.
 */
class QrCode
{
    /**
     * The SVG element only (no XML declaration), safe to put in a page: it is
     * drawn from the data given, which is a link the shop built itself.
     */
    public static function svg(string $data, int $size = 176): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($data);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
