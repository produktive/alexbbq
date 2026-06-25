<?php

namespace App\Support;

use Filament\Support\Colors\Color;

class Theme
{
    /**
     * Brand accent hex shared by Filament primary.
     *
     * Null keeps neutral zinc (matches default Flux accent in app.css).
     * When set, also update the Flux accent block in resources/css/app.css:
     *
     * | PRIMARY_HEX | Flux light (--color-accent) | Flux dark (--color-accent) |
     * |-------------|-----------------------------|----------------------------|
     * | null        | var(--color-neutral-800)    | var(--color-white)         |
     * | #ea580c     | var(--color-orange-600)     | var(--color-orange-600)    |
     * | #0d9488     | var(--color-teal-600)       | var(--color-teal-600)      |
     * | #2dd4bf     | var(--color-teal-400)       | var(--color-teal-400)      |
     * | #8b5cf6     | var(--color-violet-500)     | var(--color-violet-500)    |
     *
     * Filament table CTAs (e.g. Add Smoker) should use Flux primary buttons with
     * mountAction() — Filament button styles will not match Flux exactly.
     */
    public const ?string PRIMARY_HEX = null;

    /**
     * @return array<int | string, string | int>
     */
    public static function filamentPrimary(): array
    {
        return self::PRIMARY_HEX === null
            ? Color::Zinc
            : Color::hex(self::PRIMARY_HEX);
    }
}
