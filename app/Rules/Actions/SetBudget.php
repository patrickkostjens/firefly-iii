<?php
/**
 * SetBudget.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Rules\Actions;


use FireflyIII\Models\Budget;
use FireflyIII\Models\RuleAction;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use Log;

/**
 * Class SetBudget
 *
 * @package FireflyIII\Rules\Action
 */
class SetBudget implements ActionInterface
{

    private $action;


    /**
     * TriggerInterface constructor.
     *
     * @param RuleAction $action
     */
    public function __construct(RuleAction $action)
    {
        $this->action = $action;
    }

    /**
     * @param TransactionJournal $journal
     *
     * @return bool
     */
    public function act(TransactionJournal $journal): bool
    {
        /** @var BudgetRepositoryInterface $repository */
        $repository = app(BudgetRepositoryInterface::class);
        $repository->setUser($journal->user);
        $search  = $this->action->action_value;
        $budgets = $repository->getActiveBudgets();
        $budget  = $budgets->filter(
            function (Budget $current) use ($search) {
                return $current->name == $search;
            }
        )->first();
        if (is_null($budget)) {
            Log::debug(sprintf('RuleAction SetBudget could not set budget of journal #%d to "%s" because no such budget exists.', $journal->id, $search));

            return true;
        }

        if ($journal->transactionType->type == TransactionType::TRANSFER) {
            Log::debug(sprintf('RuleAction SetBudget could not set budget of journal #%d to "%s" because journal is a transfer.', $journal->id, $search));

            return true;
        }

        Log::debug(sprintf('RuleAction SetBudget set the budget of journal #%d to budget #%d ("%s").', $journal->id, $budget->id, $budget->name));

        $journal->budgets()->sync([$budget->id]);


        return true;
    }
}
