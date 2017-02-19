<?php
/**
 * CollectorInterface.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Export\Collector;

use FireflyIII\Models\ExportJob;
use Illuminate\Support\Collection;

/**
 * Interface CollectorInterface
 *
 * @package FireflyIII\Export\Collector
 */
interface CollectorInterface
{
    /**
     * @return Collection
     */
    public function getEntries(): Collection;

    /**
     * @return bool
     */
    public function run(): bool;

    /**
     * @param Collection $entries
     *
     * @return void
     *
     */
    public function setEntries(Collection $entries);

    /**
     * @param ExportJob $job
     *
     * @return mixed
     */
    public function setJob(ExportJob $job);

}
