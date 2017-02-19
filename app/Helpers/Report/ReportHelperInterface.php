<?php
/**
 * ReportHelperInterface.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Helpers\Report;

use Carbon\Carbon;
use FireflyIII\Helpers\Collection\Bill as BillCollection;
use FireflyIII\Helpers\Collection\Expense;
use FireflyIII\Helpers\Collection\Income;
use Illuminate\Support\Collection;

/**
 * Interface ReportHelperInterface
 *
 * @package FireflyIII\Helpers\Report
 */
interface ReportHelperInterface
{

    /**
     * This method generates a full report for the given period on all
     * the users bills and their payments.
     *
     * Excludes bills which have not had a payment on the mentioned accounts.
     *
     * @param Carbon     $start
     * @param Carbon     $end
     * @param Collection $accounts
     *
     * @return BillCollection
     */
    public function getBillReport(Carbon $start, Carbon $end, Collection $accounts): BillCollection;

    /**
     * @param Carbon $date
     *
     * @return array
     */
    public function listOfMonths(Carbon $date): array;

}
