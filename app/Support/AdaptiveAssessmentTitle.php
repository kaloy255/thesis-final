<?php

namespace App\Support;

use App\Models\Assessment;

/**
 * Keeps adaptive assessment titles readable (avoids nested "Adaptive Assessment for …" chains).
 */
class AdaptiveAssessmentTitle
{
    private const PREFIX = 'Adaptive Assessment for ';

    /**
     * Strip all leading "Adaptive Assessment for " segments so the UI shows the core title once.
     */
    public static function stripAdaptivePrefixes(string $title): string
    {
        $t = trim($title);
        $pl = strlen(self::PREFIX);

        while (str_starts_with(strtolower($t), strtolower(self::PREFIX))) {
            $t = trim(substr($t, $pl));
        }

        return $t !== '' ? $t : trim($title);
    }

    /**
     * Title shown in history trees and listings.
     */
    public static function forHistoryListing(string $title): string
    {
        return self::stripAdaptivePrefixes($title);
    }

    /**
     * Title when persisting a new adaptive assessment (single prefix + core).
     */
    public static function forNewAdaptive(Assessment $sourceAssessment): string
    {
        $core = self::stripAdaptivePrefixes($sourceAssessment->title);

        return self::PREFIX.$core;
    }
}
