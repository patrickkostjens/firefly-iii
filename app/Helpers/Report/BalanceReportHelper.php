<?php
/**
 * BalanceReportHelper.php
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
use DB;
use FireflyIII\Helpers\Collection\Balance;
use FireflyIII\Helpers\Collection\BalanceEntry;
use FireflyIII\Helpers\Collection\BalanceHeader;
use FireflyIII\Helpers\Collection\BalanceLine;
use FireflyIII\Models\BudgetLimit;
use FireflyIII\Models\Tag;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Log;

/**
 * Class BalanceReportHelper
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) // I can't really help it.
 * @package FireflyIII\Helpers\Report
 */
class BalanceReportHelper implements BalanceReportHelperInterface
{

    /** @var  BudgetRepositoryInterface */
    protected $budgetRepository;

    /**
     * ReportHelper constructor.
     *
     *
     * @param BudgetRepositoryInterface $budgetRepository
     */
    public function __construct(BudgetRepositoryInterface $budgetRepository)
    {
        $this->budgetRepository = $budgetRepository;
    }


    /**
     * @param Collection $accounts
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return Balance
     */
    public function getBalanceReport(Collection $accounts, Carbon $start, Carbon $end): Balance
    {
        Log::debug('Start of balance report');
        $balance      = new Balance;
        $header       = new BalanceHeader;
        $budgetLimits = $this->budgetRepository->getAllBudgetLimits($start, $end);
        foreach ($accounts as $account) {
            Log::debug(sprintf('Add account %s to headers.', $account->name));
            $header->addAccount($account);
        }

        /** @var BudgetLimit $budgetLimit */
        foreach ($budgetLimits as $budgetLimit) {
            $line = $this->createBalanceLine($budgetLimit, $accounts);
            $balance->addBalanceLine($line);
        }
        Log::debug('Create rest of the things.');
        $noBudgetLine       = $this->createNoBudgetLine($accounts, $start, $end);
        $coveredByTagLine   = $this->createTagsBalanceLine($accounts, $start, $end);
        $leftUnbalancedLine = $this->createLeftUnbalancedLine($noBudgetLine, $coveredByTagLine);

        $balance->addBalanceLine($noBudgetLine);
        $balance->addBalanceLine($coveredByTagLine);
        $balance->addBalanceLine($leftUnbalancedLine);
        $balance->setBalanceHeader($header);

        Log::debug('Clear unused budgets.');
        // remove budgets without expenses from balance lines:
        $balance = $this->removeUnusedBudgets($balance);

        Log::debug('Return report.');

        return $balance;
    }

    /**
     * This method collects all transfers that are part of a "balancing act" tag
     * and groups the amounts of those transfers by their destination account.
     *
     * This is used to indicate which expenses, usually outside of budgets, have been
     * corrected by transfers from a savings account.
     *
     * @param Collection $accounts
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return Collection
     */
    private function allCoveredByBalancingActs(Collection $accounts, Carbon $start, Carbon $end): Collection
    {
        $ids = $accounts->pluck('id')->toArray();
        $set = auth()->user()->tags()
                     ->leftJoin('tag_transaction_journal', 'tag_transaction_journal.tag_id', '=', 'tags.id')
                     ->leftJoin('transaction_journals', 'tag_transaction_journal.transaction_journal_id', '=', 'transaction_journals.id')
                     ->leftJoin('transaction_types', 'transaction_journals.transaction_type_id', '=', 'transaction_types.id')
                     ->leftJoin(
                         'transactions AS t_source', function (JoinClause $join) {
                         $join->on('transaction_journals.id', '=', 't_source.transaction_journal_id')->where('t_source.amount', '<', 0);
                     }
                     )
                     ->leftJoin(
                         'transactions AS t_destination', function (JoinClause $join) {
                         $join->on('transaction_journals.id', '=', 't_destination.transaction_journal_id')->where('t_destination.amount', '>', 0);
                     }
                     )
                     ->where('tags.tagMode', 'balancingAct')
                     ->where('transaction_types.type', TransactionType::TRANSFER)
                     ->where('transaction_journals.date', '>=', $start->format('Y-m-d'))
                     ->where('transaction_journals.date', '<=', $end->format('Y-m-d'))
                     ->whereNull('transaction_journals.deleted_at')
                     ->whereIn('t_source.account_id', $ids)
                     ->whereIn('t_destination.account_id', $ids)
                     ->groupBy('t_destination.account_id')
                     ->get(
                         [
                             't_destination.account_id',
                             DB::raw('SUM(t_destination.amount) AS sum'),
                         ]
                     );

        return $set;
    }


    /**
     * @param BudgetLimit $budgetLimit
     * @param Collection  $accounts
     *
     * @return BalanceLine
     */
    private function createBalanceLine(BudgetLimit $budgetLimit, Collection $accounts): BalanceLine
    {
        $line = new BalanceLine;
        $line->setBudget($budgetLimit->budget);
        $line->setBudgetLimit($budgetLimit);

        // loop accounts:
        foreach ($accounts as $account) {
            $balanceEntry = new BalanceEntry;
            $balanceEntry->setAccount($account);
            $spent = $this->budgetRepository->spentInPeriod(
                new Collection([$budgetLimit->budget]), new Collection([$account]), $budgetLimit->start_date, $budgetLimit->end_date
            );
            $balanceEntry->setSpent($spent);
            $line->addBalanceEntry($balanceEntry);
        }

        return $line;
    }

    /**
     * @param BalanceLine $noBudgetLine
     * @param BalanceLine $coveredByTagLine
     *
     * @return BalanceLine
     */
    private function createLeftUnbalancedLine(BalanceLine $noBudgetLine, BalanceLine $coveredByTagLine): BalanceLine
    {
        $line = new BalanceLine;
        $line->setRole(BalanceLine::ROLE_DIFFROLE);
        $noBudgetEntries = $noBudgetLine->getBalanceEntries();
        $tagEntries      = $coveredByTagLine->getBalanceEntries();

        foreach ($noBudgetEntries as $entry) {
            $account  = $entry->getAccount();
            $tagEntry = $tagEntries->filter(
                function (BalanceEntry $current) use ($account) {
                    return $current->getAccount()->id === $account->id;
                }
            );
            if ($tagEntry->first()) {
                // found corresponding entry. As we should:
                $newEntry = new BalanceEntry;
                $newEntry->setAccount($account);
                $spent = bcadd($tagEntry->first()->getLeft(), $entry->getSpent());
                $newEntry->setSpent($spent);
                $line->addBalanceEntry($newEntry);
            }
        }

        return $line;


    }

    /**
     * @param Collection $accounts
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return BalanceLine
     */
    private function createNoBudgetLine(Collection $accounts, Carbon $start, Carbon $end): BalanceLine
    {
        $empty = new BalanceLine;

        foreach ($accounts as $account) {
            $spent = $this->budgetRepository->spentInPeriodWoBudget(new Collection([$account]), $start, $end);
            // budget
            $budgetEntry = new BalanceEntry;
            $budgetEntry->setAccount($account);
            $budgetEntry->setSpent($spent);
            $empty->addBalanceEntry($budgetEntry);

        }

        return $empty;
    }

    /**
     * @param Collection $accounts
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return BalanceLine
     */
    private function createTagsBalanceLine(Collection $accounts, Carbon $start, Carbon $end): BalanceLine
    {
        $tags     = new BalanceLine;
        $tagsLeft = $this->allCoveredByBalancingActs($accounts, $start, $end);

        $tags->setRole(BalanceLine::ROLE_TAGROLE);

        foreach ($accounts as $account) {
            $leftEntry = $tagsLeft->filter(
                function (Tag $tag) use ($account) {
                    return $tag->account_id == $account->id;
                }
            );
            $left      = '0';
            if (!is_null($leftEntry->first())) {
                $left = $leftEntry->first()->sum;
            }

            // balanced by tags
            $tagEntry = new BalanceEntry;
            $tagEntry->setAccount($account);
            $tagEntry->setLeft($left);
            $tags->addBalanceEntry($tagEntry);

        }

        return $tags;
    }

    /**
     * @param Balance $balance
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's exactly 5.
     *
     * @return Balance
     */
    private function removeUnusedBudgets(Balance $balance): Balance
    {
        $set    = $balance->getBalanceLines();
        $newSet = new Collection;
        foreach ($set as $entry) {
            if (!is_null($entry->getBudget()->id)) {
                $sum = '0';
                foreach ($entry->getBalanceEntries() as $balanceEntry) {
                    $sum = bcadd($sum, $balanceEntry->getSpent());
                }
                if (bccomp($sum, '0') === -1) {
                    $newSet->push($entry);
                }
                continue;
            }
            $newSet->push($entry);
        }

        $balance->setBalanceLines($newSet);

        return $balance;

    }

}
