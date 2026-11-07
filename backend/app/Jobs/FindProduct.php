<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Looks a new product up on the web and decides exact match / needs a person (FR-51..FR-55). Built in the new-products step. */
class FindProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $newProductId) {}

    public function handle(): void {}
}
