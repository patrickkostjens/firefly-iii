<?php
/**
 * BudgetController.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Http\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Helpers\Collector\JournalCollectorInterface;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\Budget;
use FireflyIII\Models\BudgetLimit;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Support\CacheProperties;
use Illuminate\Support\Collection;
use Navigation;
use Preferences;
use Response;

/**
 * Class BudgetController
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) // can't realy be helped.
 *
 * @package FireflyIII\Http\Controllers\Chart
 */
class BudgetController extends Controller
{

    /** @var GeneratorInterface */
    protected $generator;

    /** @var  BudgetRepositoryInterface */
    protected $repository;

    /**
     * BudgetController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->middleware(
            function ($request, $next) {
                $this->generator  = app(GeneratorInterface::class);
                $this->repository = app(BudgetRepositoryInterface::class);

                return $next($request);
            }
        );
    }

    /**
     *
     * @param Budget $budget
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function budget(Budget $budget)
    {
        $first = $this->repository->firstUseDate($budget);
        $range = Preferences::get('viewRange', '1M')->data;
        $last  = session('end', new Carbon);

        $cache = new CacheProperties();
        $cache->addProperty($first);
        $cache->addProperty($last);
        $cache->addProperty('chart.budget.budget');

        if ($cache->has()) {
            return Response::json($cache->get());
        }

        $final = clone $last;
        $final->addYears(2);
        $budgetCollection = new Collection([$budget]);
        $last             = Navigation::endOfX($last, $range, $final); // not to overshoot.
        $entries          = [];
        while ($first < $last) {

            // periodspecific dates:
            $currentStart = Navigation::startOfPeriod($first, $range);
            $currentEnd   = Navigation::endOfPeriod($first, $range);
            // sub another day because reasons.
            $currentEnd->subDay();
            $spent            = $this->repository->spentInPeriod($budgetCollection, new Collection, $currentStart, $currentEnd);
            $format           = Navigation::periodShow($first, $range);
            $entries[$format] = bcmul($spent, '-1');
            $first            = Navigation::addPeriod($first, $range, 0);
        }

        $data = $this->generator->singleSet(strval(trans('firefly.spent')), $entries);

        $cache->store($data);

        return Response::json($data);
    }

    /**
     * Shows the amount left in a specific budget limit.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's exactly five.
     * @param Budget      $budget
     * @param BudgetLimit $budgetLimit
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws FireflyException
     */
    public function budgetLimit(Budget $budget, BudgetLimit $budgetLimit)
    {
        if ($budgetLimit->budget->id != $budget->id) {
            throw new FireflyException('This budget limit is not part of this budget.');
        }

        $start = clone $budgetLimit->start_date;
        $end   = clone $budgetLimit->end_date;
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.budget.budget.limit');
        $cache->addProperty($budgetLimit->id);

        if ($cache->has()) {
            return Response::json($cache->get());
        }

        $entries          = [];
        $amount           = $budgetLimit->amount;
        $budgetCollection = new Collection([$budget]);
        while ($start <= $end) {
            $spent            = $this->repository->spentInPeriod($budgetCollection, new Collection, $start, $start);
            $amount           = bcadd($amount, $spent);
            $format           = $start->formatLocalized(strval(trans('config.month_and_day')));
            $entries[$format] = $amount;

            $start->addDay();
        }
        $data = $this->generator->singleSet(strval(trans('firefly.left')), $entries);
        $cache->store($data);

        return Response::json($data);
    }

    /**
     * Shows a budget list with spent/left/overspent.
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's exactly five.
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength) // 46 lines, I'm fine with this.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function frontpage()
    {
        $start = session('start', Carbon::now()->startOfMonth());
        $end   = session('end', Carbon::now()->endOfMonth());
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.budget.frontpage');
        if ($cache->has()) {
            return Response::json($cache->get());
        }
        $budgets   = $this->repository->getActiveBudgets();
        $chartData = [
            ['label' => strval(trans('firefly.spent_in_budget')), 'entries' => [], 'type' => 'bar',],
            ['label' => strval(trans('firefly.left_to_spend')), 'entries' => [], 'type' => 'bar',],
            ['label' => strval(trans('firefly.overspent')), 'entries' => [], 'type' => 'bar',],
        ];


        /** @var Budget $budget */
        foreach ($budgets as $budget) {
            // get relevant repetitions:
            $limits   = $this->repository->getBudgetLimits($budget, $start, $end);
            $expenses = $this->getExpensesForBudget($limits, $budget, $start, $end);
            foreach ($expenses as $name => $row) {
                $chartData[0]['entries'][$name] = $row['spent'];
                $chartData[1]['entries'][$name] = $row['left'];
                $chartData[2]['entries'][$name] = $row['overspent'];
            }
        }
        // for no budget:
        $spent = $this->spentInPeriodWithout($start, $end);
        $name  = strval(trans('firefly.no_budget'));
        if (bccomp($spent, '0') !== 0) {
            $chartData[0]['entries'][$name] = bcmul($spent, '-1');
            $chartData[1]['entries'][$name] = '0';
            $chartData[2]['entries'][$name] = '0';
        }

        $data = $this->generator->multiSet($chartData);
        $cache->store($data);

        return Response::json($data);
    }


    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's exactly five.
     *
     * @param Budget     $budget
     * @param Carbon     $start
     * @param Carbon     $end
     * @param Collection $accounts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function period(Budget $budget, Collection $accounts, Carbon $start, Carbon $end)
    {
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($accounts);
        $cache->addProperty($budget->id);
        $cache->addProperty('chart.budget.period');
        if ($cache->has()) {
            return Response::json($cache->get());
        }
        $periods  = Navigation::listOfPeriods($start, $end);
        $entries  = $this->repository->getBudgetPeriodReport(new Collection([$budget]), $accounts, $start, $end); // get the expenses
        $budgeted = $this->getBudgetedInPeriod($budget, $start, $end);

        // join them into one set of data:
        $chartData = [
            ['label' => strval(trans('firefly.spent')), 'type' => 'bar', 'entries' => [],],
            ['label' => strval(trans('firefly.budgeted')), 'type' => 'bar', 'entries' => [],],
        ];

        foreach (array_keys($periods) as $period) {
            $label                           = $periods[$period];
            $spent                           = isset($entries[$budget->id]['entries'][$period]) ? $entries[$budget->id]['entries'][$period] : '0';
            $limit                           = isset($budgeted[$period]) ? $budgeted[$period] : 0;
            $chartData[0]['entries'][$label] = round(bcmul($spent, '-1'), 12);
            $chartData[1]['entries'][$label] = $limit;
        }
        $data = $this->generator->multiSet($chartData);
        $cache->store($data);

        return Response::json($data);
    }

    /**
     * @param Collection $accounts
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function periodNoBudget(Collection $accounts, Carbon $start, Carbon $end)
    {
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($accounts);
        $cache->addProperty('chart.budget.no-budget');
        if ($cache->has()) {
            return Response::json($cache->get());
        }

        // the expenses:
        $periods   = Navigation::listOfPeriods($start, $end);
        $entries   = $this->repository->getNoBudgetPeriodReport($accounts, $start, $end);
        $chartData = [];

        // join them:
        foreach (array_keys($periods) as $period) {
            $label             = $periods[$period];
            $spent             = isset($entries['entries'][$period]) ? $entries['entries'][$period] : '0';
            $chartData[$label] = bcmul($spent, '-1');
        }
        $data = $this->generator->singleSet(strval(trans('firefly.spent')), $chartData);
        $cache->store($data);

        return Response::json($data);
    }

    /**
     * @param Budget $budget
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array
     */
    private function getBudgetedInPeriod(Budget $budget, Carbon $start, Carbon $end): array
    {
        $key      = Navigation::preferredCarbonFormat($start, $end);
        $range    = Navigation::preferredRangeFormat($start, $end);
        $current  = clone $start;
        $budgeted = [];
        while ($current < $end) {
            $currentStart     = Navigation::startOfPeriod($current, $range);
            $currentEnd       = Navigation::endOfPeriod($current, $range);
            $budgetLimits     = $this->repository->getBudgetLimits($budget, $currentStart, $currentEnd);
            $index            = $currentStart->format($key);
            $budgeted[$index] = $budgetLimits->sum('amount');
            $currentEnd->addDay();
            $current = clone $currentEnd;
        }

        return $budgeted;
    }

    /**
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's 6 but ok.
     *
     * @param Collection $limits
     * @param Budget     $budget
     * @param Carbon     $start
     * @param Carbon     $end
     *
     * @return array
     */
    private function getExpensesForBudget(Collection $limits, Budget $budget, Carbon $start, Carbon $end): array
    {
        $return = [];
        if ($limits->count() === 0) {
            $spent = $this->repository->spentInPeriod(new Collection([$budget]), new Collection, $start, $end);
            if (bccomp($spent, '0') !== 0) {
                $return[$budget->name]['spent']     = bcmul($spent, '-1');
                $return[$budget->name]['left']      = 0;
                $return[$budget->name]['overspent'] = 0;
            }

            return $return;
        }

        $rows = $this->spentInPeriodMulti($budget, $limits);
        foreach ($rows as $name => $row) {
            if (bccomp($row['spent'], '0') !== 0 || bccomp($row['left'], '0') !== 0) {
                $return[$name]['spent']     = bcmul($row['spent'], '-1');
                $return[$name]['left']      = $row['left'];
                $return[$name]['overspent'] = bcmul($row['overspent'], '-1');
            }
        }
        unset($rows, $row);

        return $return;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's exactly five.
     *
     * Returns an array with the following values:
     * 0 =>
     *   'name' => name of budget + repetition
     *   'left' => left in budget repetition (always zero)
     *   'overspent' => spent more than budget repetition? (always zero)
     *   'spent' => actually spent in period for budget
     * 1 => (etc)
     *
     * @param Budget     $budget
     * @param Collection $limits
     *
     * @return array
     */
    private function spentInPeriodMulti(Budget $budget, Collection $limits): array
    {
        $return = [];
        $format = strval(trans('config.month_and_day'));
        $name   = $budget->name;
        /** @var BudgetLimit $budgetLimit */
        foreach ($limits as $budgetLimit) {
            $expenses = $this->repository->spentInPeriod(new Collection([$budget]), new Collection, $budgetLimit->start_date, $budgetLimit->end_date);

            if ($limits->count() > 1) {
                $name = $budget->name . ' ' . trans(
                        'firefly.between_dates',
                        [
                            'start' => $budgetLimit->start_date->formatLocalized($format),
                            'end'   => $budgetLimit->end_date->formatLocalized($format),
                        ]
                    );
            }
            $amount        = $budgetLimit->amount;
            $left          = bccomp(bcadd($amount, $expenses), '0') < 1 ? '0' : bcadd($amount, $expenses);
            $spent         = $expenses;
            $overspent     = bccomp(bcadd($amount, $expenses), '0') < 1 ? bcadd($amount, $expenses) : '0';
            $return[$name] = [
                'left'      => $left,
                'overspent' => $overspent,
                'spent'     => $spent,
            ];
        }

        return $return;
    }

    /**
     * Returns an array with the following values:
     * 'name' => "no budget" in local language
     * 'repetition_left' => left in budget repetition (always zero)
     * 'repetition_overspent' => spent more than budget repetition? (always zero)
     * 'spent' => actually spent in period for budget
     *
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return string
     */
    private function spentInPeriodWithout(Carbon $start, Carbon $end): string
    {
        // collector
        /** @var JournalCollectorInterface $collector */
        $collector = app(JournalCollectorInterface::class);
        $types     = [TransactionType::WITHDRAWAL];
        $collector->setAllAssetAccounts()->setTypes($types)->setRange($start, $end)->withoutBudget();
        $journals = $collector->getJournals();
        $sum      = '0';
        /** @var Transaction $entry */
        foreach ($journals as $entry) {
            $sum = bcadd($entry->transaction_amount, $sum);
        }

        return $sum;
    }
}
