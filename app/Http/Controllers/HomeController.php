<?php
/**
 * HomeController.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);
namespace FireflyIII\Http\Controllers;

use Artisan;
use Carbon\Carbon;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Helpers\Collector\JournalCollectorInterface;
use FireflyIII\Models\AccountType;
use FireflyIII\Repositories\Account\AccountRepositoryInterface as ARI;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Log;
use Preferences;
use Session;
use View;

/**
 * Class HomeController
 *
 * @package FireflyIII\Http\Controllers
 */
class HomeController extends Controller
{
    /**
     * HomeController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        View::share('title', 'Firefly III');
        View::share('mainTitleIcon', 'fa-fire');
    }

    /**
     * @param Request $request
     */
    public function dateRange(Request $request)
    {

        $start         = new Carbon($request->get('start'));
        $end           = new Carbon($request->get('end'));
        $label         = $request->get('label');
        $isCustomRange = false;

        Log::debug('Received dateRange', ['start' => $request->get('start'), 'end' => $request->get('end'), 'label' => $request->get('label')]);

        // check if the label is "everything" or "Custom range" which will betray
        // a possible problem with the budgets.
        if ($label === strval(trans('firefly.everything')) || $label === strval(trans('firefly.customRange'))) {
            $isCustomRange = true;
            Log::debug('Range is now marked as "custom".');
        }

        $diff = $start->diffInDays($end);

        if ($diff > 50) {
            Session::flash('warning', strval(trans('firefly.warning_much_data', ['days' => $diff])));
        }

        Session::put('is_custom_range', $isCustomRange);
        Session::put('start', $start);
        Session::put('end', $end);
    }

    /**
     * @throws FireflyException
     */
    public function displayError()
    {
        throw new FireflyException('A very simple test error.');
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function flush(Request $request)
    {
        Preferences::mark();
        $request->session()->forget(['start', 'end', '_previous', 'viewRange', 'range', 'is_custom_range']);
        Artisan::call('cache:clear');

        return redirect(route('index'));
    }

    /**
     * @param ARI $repository
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|View
     */
    public function index(ARI $repository)
    {
        $types = config('firefly.accountTypesByIdentifier.asset');
        $count = $repository->count($types);

        if ($count == 0) {
            return redirect(route('new-user.index'));
        }

        $subTitle     = trans('firefly.welcomeBack');
        $transactions = [];
        $frontPage    = Preferences::get(
            'frontPageAccounts', $repository->getAccountsByType([AccountType::DEFAULT, AccountType::ASSET])->pluck('id')->toArray()
        );
        /** @var Carbon $start */
        $start = session('start', Carbon::now()->startOfMonth());
        /** @var Carbon $end */
        $end                   = session('end', Carbon::now()->endOfMonth());
        $showTour              = Preferences::get('tour', true)->data;
        $accounts              = $repository->getAccountsById($frontPage->data);
        $showDepositsFrontpage = Preferences::get('showDepositsFrontpage', false)->data;

        // zero bills? Hide some elements from view.
        /** @var BillRepositoryInterface $billRepository */
        $billRepository = app(BillRepositoryInterface::class);
        $billCount      = $billRepository->getBills()->count();

        foreach ($accounts as $account) {
            $collector = app(JournalCollectorInterface::class);
            $collector->setAccounts(new Collection([$account]))->setRange($start, $end)->setLimit(10)->setPage(1);
            $set            = $collector->getJournals();
            $transactions[] = [$set, $account];
        }

        return view(
            'index', compact('count', 'showTour', 'title', 'subTitle', 'mainTitleIcon', 'transactions', 'showDepositsFrontpage', 'billCount')
        );
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function testFlash()
    {
        Session::flash('success', 'This is a success message.');
        Session::flash('info', 'This is an info message.');
        Session::flash('warning', 'This is a warning.');
        Session::flash('error', 'This is an error!');

        return redirect(route('home'));
    }

}
