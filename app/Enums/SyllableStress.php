<?php

namespace App\Enums;

/**
 * How strongly a syllable of sung text is stressed.
 */
enum SyllableStress
{
    case Primary;
    case Secondary;
    case None;
}
