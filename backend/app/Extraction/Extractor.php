<?php

namespace App\Extraction;

use App\Models\Visit;

/** Reads the card and shelf photos of a visit (FR-20..FR-23). Chosen by EXTRACTION_DRIVER. */
interface Extractor
{
    /** Read the photos and store the result (directly, or later via a callback). */
    public function start(Visit $visit): void;
}
