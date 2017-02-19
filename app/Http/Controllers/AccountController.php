<?php
/**
 * AccountController.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Http\Controllers;

use Amount;
use Carbon\Carbon;
use ExpandedForm;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Helpers\Collector\JournalCollectorInterface;
use FireflyIII\Http\Requests\AccountFormRequest;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\Transaction;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Account\AccountTaskerInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Support\CacheProperties;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Log;
use Navigation;
use Preferences;
use Steam;
use View;

/**
 * Class AccountController
 *
 * @package FireflyIII\Http\Controllers
 */
class AccountController extends Controller
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        // translations:
        $this->middleware(
            function ($request, $next) {
                View::share('mainTitleIcon', 'fa-credit-card');
                View::share('title', trans('firefly.accounts'));

                return $next($request);
            }
        );
    }

    /**
     * @param Request $request
     * @param string  $what
     *
     * @return View
     */
    public function create(Request $request, string $what = 'asset')
    {
        /** @var CurrencyRepositoryInterface $repository */
        $repository      = app(CurrencyRepositoryInterface::class);
        $currencies      = ExpandedForm::makeSelectList($repository->get());
        $defaultCurrency = Amount::getDefaultCurrency();
        $subTitleIcon    = config('firefly.subIconsByIdentifier.' . $what);
        $subTitle        = trans('firefly.make_new_' . $what . '_account');
        $roles           = [];
        foreach (config('firefly.accountRoles') as $role) {
            $roles[$role] = strval(trans('firefly.account_role_' . $role));
        }


        // pre fill some data
        $request->session()->flash('preFilled', ['currency_id' => $defaultCurrency->id,]);

        // put previous url in session if not redirect from store (not "create another").
        if (session('accounts.create.fromStore') !== true) {
            $this->rememberPreviousUri('accounts.create.uri');
        }
        $request->session()->forget('accounts.create.fromStore');
        $request->session()->flash('gaEventCategory', 'accounts');
        $request->session()->flash('gaEventAction', 'create-' . $what);

        return view('accounts.create', compact('subTitleIcon', 'what', 'subTitle', 'currencies', 'roles'));

    }

    /**
     * @param Request                    $request
     * @param AccountRepositoryInterface $repository
     * @param Account                    $account
     *
     * @return View
     */
    public function delete(Request $request, AccountRepositoryInterface $repository, Account $account)
    {
        $typeName    = config('firefly.shortNamesByFullName.' . $account->accountType->type);
        $subTitle    = trans('firefly.delete_' . $typeName . '_account', ['name' => $account->name]);
        $accountList = ExpandedForm::makeSelectListWithEmpty($repository->getAccountsByType([$account->accountType->type]));
        unset($accountList[$account->id]);

        // put previous url in session
        $this->rememberPreviousUri('accounts.delete.uri');
        $request->session()->flash('gaEventCategory', 'accounts');
        $request->session()->flash('gaEventAction', 'delete-' . $typeName);

        return view('accounts.delete', compact('account', 'subTitle', 'accountList'));
    }

    /**
     * @param Request                    $request
     * @param AccountRepositoryInterface $repository
     * @param Account                    $account
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request, AccountRepositoryInterface $repository, Account $account)
    {
        $type     = $account->accountType->type;
        $typeName = config('firefly.shortNamesByFullName.' . $type);
        $name     = $account->name;
        $moveTo   = $repository->find(intval($request->get('move_account_before_delete')));

        $repository->destroy($account, $moveTo);

        $request->session()->flash('success', strval(trans('firefly.' . $typeName . '_deleted', ['name' => $name])));
        Preferences::mark();

        return redirect($this->getPreviousUri('accounts.delete.uri'));
    }

    /**
     * @param Request $request
     * @param Account $account
     *
     * @return View
     */
    public function edit(Request $request, Account $account)
    {

        $what         = config('firefly.shortNamesByFullName')[$account->accountType->type];
        $subTitle     = trans('firefly.edit_' . $what . '_account', ['name' => $account->name]);
        $subTitleIcon = config('firefly.subIconsByIdentifier.' . $what);
        /** @var CurrencyRepositoryInterface $repository */
        $repository = app(CurrencyRepositoryInterface::class);
        $currencies = ExpandedForm::makeSelectList($repository->get());
        $roles      = [];
        foreach (config('firefly.accountRoles') as $role) {
            $roles[$role] = strval(trans('firefly.account_role_' . $role));
        }


        // put previous url in session if not redirect from store (not "return_to_edit").
        if (session('accounts.edit.fromUpdate') !== true) {
            $this->rememberPreviousUri('accounts.edit.uri');
        }
        $request->session()->forget('accounts.edit.fromUpdate');

        // pre fill some useful values.

        // the opening balance is tricky:
        $openingBalanceAmount = $account->getOpeningBalanceAmount();
        $openingBalanceAmount = $account->getOpeningBalanceAmount() === '0' ? '' : $openingBalanceAmount;
        $openingBalanceDate   = $account->getOpeningBalanceDate();
        $openingBalanceDate   = $openingBalanceDate->year === 1900 ? null : $openingBalanceDate->format('Y-m-d');

        $preFilled = [
            'accountNumber'        => $account->getMeta('accountNumber'),
            'accountRole'          => $account->getMeta('accountRole'),
            'ccType'               => $account->getMeta('ccType'),
            'ccMonthlyPaymentDate' => $account->getMeta('ccMonthlyPaymentDate'),
            'BIC'                  => $account->getMeta('BIC'),
            'openingBalanceDate'   => $openingBalanceDate,
            'openingBalance'       => $openingBalanceAmount,
            'virtualBalance'       => $account->virtual_balance,
            'currency_id'          => $account->getMeta('currency_id'),
        ];
        $request->session()->flash('preFilled', $preFilled);
        $request->session()->flash('gaEventCategory', 'accounts');
        $request->session()->flash('gaEventAction', 'edit-' . $what);

        return view('accounts.edit', compact('currencies', 'account', 'subTitle', 'subTitleIcon', 'openingBalance', 'what', 'roles'));
    }

    /**
     * @param AccountRepositoryInterface $repository
     * @param string                     $what
     *
     * @return View
     */
    public function index(AccountRepositoryInterface $repository, string $what)
    {
        $what         = $what ?? 'asset';
        $subTitle     = trans('firefly.' . $what . '_accounts');
        $subTitleIcon = config('firefly.subIconsByIdentifier.' . $what);
        $types        = config('firefly.accountTypesByIdentifier.' . $what);
        $accounts     = $repository->getAccountsByType($types);
        /** @var Carbon $start */
        $start = clone session('start', Carbon::now()->startOfMonth());
        /** @var Carbon $end */
        $end = clone session('end', Carbon::now()->endOfMonth());
        $start->subDay();

        $ids           = $accounts->pluck('id')->toArray();
        $startBalances = Steam::balancesById($ids, $start);
        $endBalances   = Steam::balancesById($ids, $end);
        $activities    = Steam::getLastActivities($ids);

        $accounts->each(
            function (Account $account) use ($activities, $startBalances, $endBalances) {
                $account->lastActivityDate = $this->isInArray($activities, $account->id);
                $account->startBalance     = $this->isInArray($startBalances, $account->id);
                $account->endBalance       = $this->isInArray($endBalances, $account->id);
                $account->difference       = bcsub($account->endBalance, $account->startBalance);
            }
        );

        return view('accounts.index', compact('what', 'subTitleIcon', 'subTitle', 'accounts'));
    }

    /**
     * @param Request                   $request
     * @param JournalCollectorInterface $collector
     * @param Account                   $account
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|View
     */
    public function show(Request $request, JournalCollectorInterface $collector, Account $account)
    {
        if ($account->accountType->type === AccountType::INITIAL_BALANCE) {
            return $this->redirectToOriginalAccount($account);
        }
        // show journals from current period only:
        $subTitleIcon = config('firefly.subIconsByIdentifier.' . $account->accountType->type);
        $subTitle     = $account->name;
        $range        = Preferences::get('viewRange', '1M')->data;
        $start        = session('start', Navigation::startOfPeriod(new Carbon, $range));
        $end          = session('end', Navigation::endOfPeriod(new Carbon, $range));
        $page         = intval($request->get('page')) === 0 ? 1 : intval($request->get('page'));
        $pageSize     = intval(Preferences::get('transactionPageSize', 50)->data);
        $chartUri     = route('chart.account.single', [$account->id]);
        $accountType  = $account->accountType->type;

        // grab those journals:
        $collector->setAccounts(new Collection([$account]))->setRange($start, $end)->setLimit($pageSize)->setPage($page);
        $journals = $collector->getPaginatedJournals();
        $journals->setPath('accounts/show/' . $account->id);

        // generate entries for each period (and cache those)
        $entries = $this->periodEntries($account);

        return view('accounts.show', compact('account', 'accountType', 'entries', 'subTitleIcon', 'journals', 'subTitle', 'start', 'end', 'chartUri'));
    }

    /**
     * @param Request                    $request
     * @param AccountRepositoryInterface $repository
     * @param Account                    $account
     *
     * @return View
     */
    public function showAll(Request $request, AccountRepositoryInterface $repository, Account $account)
    {
        $subTitle = sprintf('%s (%s)', $account->name, strtolower(trans('firefly.everything')));
        $page     = intval($request->get('page')) === 0 ? 1 : intval($request->get('page'));
        $pageSize = intval(Preferences::get('transactionPageSize', 50)->data);
        $chartUri = route('chart.account.all', [$account->id]);

        // replace with journal collector:
        /** @var JournalCollectorInterface $collector */
        $collector = app(JournalCollectorInterface::class);
        $collector->setUser(auth()->user());
        $collector->setAccounts(new Collection([$account]))->setLimit($pageSize)->setPage($page);
        $journals = $collector->getPaginatedJournals();
        $journals->setPath('accounts/show/' . $account->id . '/all');

        // get oldest and newest journal for account:
        $start = $repository->oldestJournalDate($account);
        $end   = $repository->newestJournalDate($account);

        // same call, except "entries".
        return view('accounts.show', compact('account', 'subTitleIcon', 'journals', 'subTitle', 'start', 'end', 'chartUri'));
    }

    /**
     * @param Request $request
     * @param Account $account
     * @param string  $date
     *
     * @return View
     */
    public function showByDate(Request $request, Account $account, string $date)
    {
        $carbon      = new Carbon($date);
        $range       = Preferences::get('viewRange', '1M')->data;
        $start       = Navigation::startOfPeriod($carbon, $range);
        $end         = Navigation::endOfPeriod($carbon, $range);
        $subTitle    = $account->name . ' (' . Navigation::periodShow($start, $range) . ')';
        $page        = intval($request->get('page')) === 0 ? 1 : intval($request->get('page'));
        $pageSize    = intval(Preferences::get('transactionPageSize', 50)->data);
        $chartUri    = route('chart.account.period', [$account->id, $carbon->format('Y-m-d')]);
        $accountType = $account->accountType->type;

        // replace with journal collector:
        /** @var JournalCollectorInterface $collector */
        $collector = app(JournalCollectorInterface::class);
        $collector->setAccounts(new Collection([$account]))->setRange($start, $end)->setLimit($pageSize)->setPage($page);
        $journals = $collector->getPaginatedJournals();
        $journals->setPath('accounts/show/' . $account->id . '/' . $date);

        // generate entries for each period (and cache those)
        $entries = $this->periodEntries($account);

        // same call, except "entries".
        return view('accounts.show', compact('account', 'accountType', 'entries', 'subTitleIcon', 'journals', 'subTitle', 'start', 'end', 'chartUri'));
    }

    /**
     * @param AccountFormRequest         $request
     * @param AccountRepositoryInterface $repository
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     */
    public function store(AccountFormRequest $request, AccountRepositoryInterface $repository)
    {
        $data    = $request->getAccountData();
        $account = $repository->store($data);

        $request->session()->flash('success', strval(trans('firefly.stored_new_account', ['name' => $account->name])));
        Preferences::mark();

        // update preferences if necessary:
        $frontPage = Preferences::get('frontPageAccounts', [])->data;
        if (count($frontPage) > 0) {
            $frontPage[] = $account->id;
            Preferences::set('frontPageAccounts', $frontPage);
        }

        if (intval($request->get('create_another')) === 1) {
            // set value so create routine will not overwrite URL:
            $request->session()->put('accounts.create.fromStore', true);

            return redirect(route('accounts.create', [$request->input('what')]))->withInput();
        }

        // redirect to previous URL.
        return redirect($this->getPreviousUri('accounts.create.uri'));
    }

    /**
     * @param AccountFormRequest         $request
     * @param AccountRepositoryInterface $repository
     * @param Account                    $account
     *
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(AccountFormRequest $request, AccountRepositoryInterface $repository, Account $account)
    {
        $data = $request->getAccountData();
        $repository->update($account, $data);

        $request->session()->flash('success', strval(trans('firefly.updated_account', ['name' => $account->name])));
        Preferences::mark();

        if (intval($request->get('return_to_edit')) === 1) {
            // set value so edit routine will not overwrite URL:
            $request->session()->put('accounts.edit.fromUpdate', true);

            return redirect(route('accounts.edit', [$account->id]))->withInput(['return_to_edit' => 1]);
        }

        // redirect to previous URL.
        return redirect($this->getPreviousUri('accounts.edit.uri'));

    }


    /**
     * @param array $array
     * @param int   $entryId
     *
     * @return null|mixed
     */
    protected function isInArray(array $array, int $entryId)
    {
        if (isset($array[$entryId])) {
            return $array[$entryId];
        }

        return '0';
    }

    /**
     * This method returns "period entries", so nov-2015, dec-2015, etc etc (this depends on the users session range)
     * and for each period, the amount of money spent and earned. This is a complex operation which is cached for
     * performance reasons.
     *
     * @param Account $account The account involved.
     *
     * @return Collection
     */
    private function periodEntries(Account $account): Collection
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);
        /** @var AccountTaskerInterface $tasker */
        $tasker = app(AccountTaskerInterface::class);

        $start   = $repository->oldestJournalDate($account);
        $range   = Preferences::get('viewRange', '1M')->data;
        $start   = Navigation::startOfPeriod($start, $range);
        $end     = Navigation::endOfX(new Carbon, $range);
        $entries = new Collection;

        // properties for cache
        $cache = new CacheProperties;
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('account-show-period-entries');
        $cache->addProperty($account->id);

        if ($cache->has()) {
            Log::debug('Entries are cached, return cache.');

            return $cache->get();
        }

        // only include asset accounts when this account is an asset:
        $assets = new Collection;
        if (in_array($account->accountType->type, [AccountType::ASSET, AccountType::DEFAULT])) {
            $assets = $repository->getAccountsByType([AccountType::ASSET, AccountType::DEFAULT]);
        }
        Log::debug('Going to get period expenses and incomes.');
        while ($end >= $start) {
            $end        = Navigation::startOfPeriod($end, $range);
            $currentEnd = Navigation::endOfPeriod($end, $range);
            $spent      = $tasker->amountOutInPeriod(new Collection([$account]), $assets, $end, $currentEnd);
            $earned     = $tasker->amountInInPeriod(new Collection([$account]), $assets, $end, $currentEnd);
            $dateStr    = $end->format('Y-m-d');
            $dateName   = Navigation::periodShow($end, $range);
            $entries->push([$dateStr, $dateName, $spent, $earned, clone $end]);
            $end = Navigation::subtractPeriod($end, $range, 1);

        }
        $cache->store($entries);

        return $entries;
    }

    /**
     * @param Account $account
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws FireflyException
     */
    private function redirectToOriginalAccount(Account $account)
    {
        /** @var Transaction $transaction */
        $transaction = $account->transactions()->first();
        if (is_null($transaction)) {
            throw new FireflyException('Expected a transaction. This account has none. BEEP, error.');
        }

        $journal = $transaction->transactionJournal;
        /** @var Transaction $opposingTransaction */
        $opposingTransaction = $journal->transactions()->where('transactions.id', '!=', $transaction->id)->first();

        if (is_null($opposingTransaction)) {
            throw new FireflyException('Expected an opposing transaction. This account has none. BEEP, error.');
        }

        return redirect(route('accounts.show', [$opposingTransaction->account_id]));
    }
}
