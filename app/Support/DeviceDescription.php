<?php

namespace App\Support;

/**
 * Something a person can recognise their own laptop by.
 *
 * Deliberately crude. A real user-agent parser is a dependency, and the question
 * this answers is only ever "which of my two screens is this" — asked once by a
 * phone listing the devices it has signed in, and again by a phone choosing
 * which screen to drive.
 */
class DeviceDescription
{
    /** @var array<string, string> */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
    ];

    /** @var array<string, string> */
    private const SYSTEMS = [
        'Windows' => 'Windows',
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    public static function of(?string $userAgent): string
    {
        $agent = $userAgent ?? '';

        $browser = self::match($agent, self::BROWSERS);
        $system = self::match($agent, self::SYSTEMS);

        if ($browser !== null && $system !== null) {
            return __(':browser on :system', ['browser' => $browser, 'system' => $system]);
        }

        return $browser ?? $system ?? __('Unknown device');
    }

    /**
     * @param  array<string, string>  $names
     */
    private static function match(string $agent, array $names): ?string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
