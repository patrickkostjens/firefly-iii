<?php
/**
 * Steam.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Support;

use Carbon\Carbon;
use Crypt;
use DB;
use FireflyIII\Models\Account;
use FireflyIII\Models\Transaction;

/**
 * Class Steam
 *
 * @package FireflyIII\Support
 */
class Steam
{

    /**
     *
     * @param \FireflyIII\Models\Account $account
     * @param \Carbon\Carbon             $date
     *
     * @return string
     */
    public function balance(Account $account, Carbon $date): string
    {

        // abuse chart properties:
        $cache = new CacheProperties;
        $cache->addProperty($account->id);
        $cache->addProperty('balance');
        $cache->addProperty($date);
        if ($cache->has()) {
            return $cache->get();
        }

        $balance = strval(
            $account->transactions()->leftJoin(
                'transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id'
            )->where('transaction_journals.date', '<=', $date->format('Y-m-d'))->sum('transactions.amount')
        );
        $virtual = is_null($account->virtual_balance) ? '0' : strval($account->virtual_balance);
        $balance = bcadd($balance, $virtual);
        $cache->store($balance);

        return $balance;
    }

    /**
     *
     * @param \FireflyIII\Models\Account $account
     * @param \Carbon\Carbon             $date
     *
     * @return string
     */
    public function balanceIgnoreVirtual(Account $account, Carbon $date): string
    {

        // abuse chart properties:
        $cache = new CacheProperties;
        $cache->addProperty($account->id);
        $cache->addProperty('balance-no-virtual');
        $cache->addProperty($date);
        if ($cache->has()) {
            return $cache->get();
        }

        $balance = strval(
            $account->transactions()->leftJoin(
                'transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id'
            )->where('transaction_journals.date', '<=', $date->format('Y-m-d'))->sum('transactions.amount')
        );

        $cache->store($balance);

        return $balance;
    }

    /**
     * Gets the balance for the given account during the whole range, using this format:
     *
     * [yyyy-mm-dd] => 123,2
     *
     * @param \FireflyIII\Models\Account $account
     * @param \Carbon\Carbon             $start
     * @param \Carbon\Carbon             $end
     *
     * @return array
     */
    public function balanceInRange(Account $account, Carbon $start, Carbon $end): array
    {
        // abuse chart properties:
        $cache = new CacheProperties;
        $cache->addProperty($account->id);
        $cache->addProperty('balance-in-range');
        $cache->addProperty($start);
        $cache->addProperty($end);
        if ($cache->has()) {
            return $cache->get();
        }

        $balances = [];
        $start->subDay();
        $end->addDay();
        $startBalance                      = $this->balance($account, $start);
        $balances[$start->format('Y-m-d')] = $startBalance;
        $start->addDay();

        // query!
        $set            = $account->transactions()
                                  ->leftJoin('transaction_journals', 'transactions.transaction_journal_id', '=', 'transaction_journals.id')
                                  ->where('transaction_journals.date', '>=', $start->format('Y-m-d'))
                                  ->where('transaction_journals.date', '<=', $end->format('Y-m-d'))
                                  ->groupBy('transaction_journals.date')
                                  ->orderBy('transaction_journals.date', 'ASC')
                                  ->whereNull('transaction_journals.deleted_at')
                                  ->get(['transaction_journals.date', DB::raw('SUM(transactions.amount) AS modified')]);
        $currentBalance = $startBalance;
        foreach ($set as $entry) {
            $modified               = is_null($entry->modified) ? '0' : strval($entry->modified);
            $currentBalance         = bcadd($currentBalance, $modified);
            $balances[$entry->date] = $currentBalance;
        }

        $cache->store($balances);

        return $balances;


    }

    /**
     * This method always ignores the virtual balance.
     *
     * @param array          $ids
     * @param \Carbon\Carbon $date
     *
     * @return array
     */
    public function balancesById(array $ids, Carbon $date): array
    {

        // cache this property.
        $cache = new CacheProperties;
        $cache->addProperty($ids);
        $cache->addProperty('balances');
        $cache->addProperty($date);
        if ($cache->has()) {
            return $cache->get();
        }

        $balances = Transaction::leftJoin('transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id')
                               ->where('transaction_journals.date', '<=', $date->format('Y-m-d'))
                               ->groupBy('transactions.account_id')
                               ->whereIn('transactions.account_id', $ids)
                               ->whereNull('transaction_journals.deleted_at')
                               ->get(['transactions.account_id', DB::raw('sum(transactions.amount) AS aggregate')]);

        $result = [];
        foreach ($balances as $entry) {
            $accountId          = intval($entry->account_id);
            $balance            = $entry->aggregate;
            $result[$accountId] = $balance;
        }


        $cache->store($result);

        return $result;
    }

    /**
     * @param int $isEncrypted
     * @param     $value
     *
     * @return string
     */
    public function decrypt(int $isEncrypted, string $value)
    {
        if ($isEncrypted === 1) {
            return Crypt::decrypt($value);
        }

        return $value;
    }

    /**
     * @param array $accounts
     *
     * @return array
     */
    public function getLastActivities(array $accounts): array
    {
        $list = [];

        $set = auth()->user()->transactions()
                     ->whereIn('transactions.account_id', $accounts)
                     ->groupBy(['transactions.account_id', 'transaction_journals.user_id'])
                     ->get(['transactions.account_id', DB::raw('MAX(transaction_journals.date) AS max_date')]);

        foreach ($set as $entry) {
            $list[intval($entry->account_id)] = new Carbon($entry->max_date);
        }

        return $list;
    }

    /**
     * @param $string
     *
     * @return int
     */
    public function phpBytes($string): int
    {
        $string = strtolower($string);

        if (!(stripos($string, 'k') === false)) {
            // has a K in it, remove the K and multiply by 1024.
            $bytes = bcmul(rtrim($string, 'kK'), '1024');

            return intval($bytes);
        }

        if (!(stripos($string, 'm') === false)) {
            // has a M in it, remove the M and multiply by 1048576.
            $bytes = bcmul(rtrim($string, 'mM'), '1048576');

            return intval($bytes);
        }

        if (!(stripos($string, 'g') === false)) {
            // has a G in it, remove the G and multiply by (1024)^3.
            $bytes = bcmul(rtrim($string, 'gG'), '1073741824');

            return intval($bytes);
        }

        return intval($string);


    }

    // parse PHP size:

    /**
     * @param string $amount
     *
     * @return string
     */
    public function positive(string $amount): string
    {
        if (bccomp($amount, '0') === -1) {
            $amount = bcmul($amount, '-1');
        }

        return $amount;
    }

}
