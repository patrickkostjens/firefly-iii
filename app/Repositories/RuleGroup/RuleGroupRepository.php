<?php
declare(strict_types = 1);

namespace FireflyIII\Repositories\RuleGroup;


use Auth;
use FireflyIII\Models\Rule;
use FireflyIII\Models\RuleGroup;
use FireflyIII\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Log;
/**
 * Class RuleGroupRepository
 *
 * @package FireflyIII\Repositories\RuleGroup
 */
class RuleGroupRepository implements RuleGroupRepositoryInterface
{
    /** @var User */
    private $user;

    /**
     * BillRepository constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        Log::debug('Constructed piggy bank repository for user #' . $user->id . ' (' . $user->email . ')');
        $this->user = $user;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->user->ruleGroups()->count();
    }

    /**
     * @param RuleGroup $ruleGroup
     * @param RuleGroup $moveTo
     *
     * @return boolean
     */
    public function destroy(RuleGroup $ruleGroup, RuleGroup $moveTo = null)
    {
        /** @var Rule $rule */
        foreach ($ruleGroup->rules as $rule) {

            if (is_null($moveTo)) {

                $rule->delete();
            } else {
                // move
                $rule->ruleGroup()->associate($moveTo);
                $rule->save();
            }
        }

        $ruleGroup->delete();

        $this->resetRuleGroupOrder();
        if (!is_null($moveTo)) {
            $this->resetRulesInGroupOrder($moveTo);
        }

        return true;
    }

    /**
     * @return Collection
     */
    public function get()
    {
        return $this->user->ruleGroups()->orderBy('order', 'ASC')->get();
    }

    /**
     * @return int
     */
    public function getHighestOrderRuleGroup()
    {
        $entry = $this->user->ruleGroups()->max('order');

        return intval($entry);
    }

    /**
     * @param User $user
     *
     * @return Collection
     */
    public function getRuleGroupsWithRules(User $user): Collection
    {
        return $user->ruleGroups()
                    ->orderBy('active', 'DESC')
                    ->orderBy('order', 'ASC')
                    ->with(
                        [
                            'rules'              => function (HasMany $query) {
                                $query->orderBy('active', 'DESC');
                                $query->orderBy('order', 'ASC');

                            },
                            'rules.ruleTriggers' => function (HasMany $query) {
                                $query->orderBy('order', 'ASC');
                            },
                            'rules.ruleActions'  => function (HasMany $query) {
                                $query->orderBy('order', 'ASC');
                            },
                        ]
                    )->get();
    }

    /**
     * @param RuleGroup $ruleGroup
     *
     * @return bool
     */
    public function moveDown(RuleGroup $ruleGroup)
    {
        $order = $ruleGroup->order;

        // find the rule with order+1 and give it order-1
        $other = $this->user->ruleGroups()->where('order', ($order + 1))->first();
        if ($other) {
            $other->order = ($other->order - 1);
            $other->save();
        }

        $ruleGroup->order = ($ruleGroup->order + 1);
        $ruleGroup->save();
        $this->resetRuleGroupOrder();
    }

    /**
     * @param RuleGroup $ruleGroup
     *
     * @return bool
     */
    public function moveUp(RuleGroup $ruleGroup)
    {
        $order = $ruleGroup->order;

        // find the rule with order-1 and give it order+1
        $other = $this->user->ruleGroups()->where('order', ($order - 1))->first();
        if ($other) {
            $other->order = ($other->order + 1);
            $other->save();
        }

        $ruleGroup->order = ($ruleGroup->order - 1);
        $ruleGroup->save();
        $this->resetRuleGroupOrder();
    }

    /**
     * @return bool
     */
    public function resetRuleGroupOrder()
    {
        $this->user->ruleGroups()->whereNotNull('deleted_at')->update(['order' => 0]);

        $set   = $this->user->ruleGroups()->where('active', 1)->orderBy('order', 'ASC')->get();
        $count = 1;
        /** @var RuleGroup $entry */
        foreach ($set as $entry) {
            $entry->order = $count;
            $entry->save();
            $count++;
        }


        return true;
    }

    /**
     * @param RuleGroup $ruleGroup
     *
     * @return bool
     */
    public function resetRulesInGroupOrder(RuleGroup $ruleGroup)
    {
        $ruleGroup->rules()->whereNotNull('deleted_at')->update(['order' => 0]);

        $set   = $ruleGroup->rules()
                           ->orderBy('order', 'ASC')
                           ->orderBy('updated_at', 'DESC')
                           ->get();
        $count = 1;
        /** @var Rule $entry */
        foreach ($set as $entry) {
            $entry->order = $count;
            $entry->save();
            $count++;
        }

        return true;

    }

    /**
     * @param array $data
     *
     * @return RuleGroup
     */
    public function store(array $data)
    {
        $order = $this->getHighestOrderRuleGroup();

        $newRuleGroup = new RuleGroup(
            [
                'user_id'     => $data['user_id'],
                'title'       => $data['title'],
                'description' => $data['description'],
                'order'       => ($order + 1),
                'active'      => 1,


            ]
        );
        $newRuleGroup->save();
        $this->resetRuleGroupOrder();

        return $newRuleGroup;
    }

    /**
     * @param RuleGroup $ruleGroup
     * @param array     $data
     *
     * @return RuleGroup
     */
    public function update(RuleGroup $ruleGroup, array $data)
    {
        // update the account:
        $ruleGroup->title       = $data['title'];
        $ruleGroup->description = $data['description'];
        $ruleGroup->active      = $data['active'];
        $ruleGroup->save();
        $this->resetRuleGroupOrder();

        return $ruleGroup;
    }
}
