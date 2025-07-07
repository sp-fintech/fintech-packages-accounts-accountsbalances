<?php

namespace Apps\Fintech\Packages\Accounts\Balances;

use Apps\Fintech\Packages\Accounts\Balances\Model\AppsFintechAccountsBalances;
use Apps\Fintech\Packages\Accounts\Users\Model\AppsFintechAccountsUsers;
use System\Base\BasePackage;

class AccountsBalances extends BasePackage
{
    protected $modelToUse = AppsFintechAccountsBalances::class;

    protected $packageName = 'accountsbalances';

    public $accountsbalances;

    public function getAccountsBalancesById($id)
    {
        $accountsbalances = $this->getById($id);

        if ($accountsbalances) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }

    public function addAccountsBalances($data)
    {
        $data['account_id'] = $this->access->auth->account()['id'];
        if (!isset($data['type'])) {
            $data['type'] = 'debit';
        }

        if ($data['type'] === 'credit') {
            $equity_balance = $this->recalculateUserEquity($data)['equity_balance'];

            if ((float) $data['amount'] > $equity_balance) {
                $this->addResponse('Cannot credit more than balance. Balance available : ' . $equity_balance, 1);

                return false;
            }
        }

        if ($this->add($data)) {
            $this->recalculateUserEquity($data);

            $this->addResponse('Balance Added');
        } else {
            $this->addResponse('Error Adding Balance', 1);
        }
    }

    public function updateAccountsBalances($data)
    {
        $accountsbalance = $this->getById((int) $data['id']);

        if ($accountsbalance) {
            if ($accountsbalance['type'] !== 'debit' &&
                $accountsbalance['type'] !== 'credit'
            ) {
                $this->addResponse('Only Debit or Credit can be updated!', 1);

                return false;
            }

            $data = array_merge($accountsbalance, $data);

            if ($this->update($data)) {
                $this->recalculateUserEquity($data);

                $this->addResponse('Success');

                return true;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function removeAccountsBalances($data)
    {
        $accountsbalance = $this->getById((int) $data['id']);

        if ($accountsbalance) {
            if ($accountsbalance['type'] !== 'debit' &&
                $accountsbalance['type'] !== 'credit'
            ) {
                $this->addResponse('Only Debit or Credit can be removed!', 1);

                return false;
            }

            if ($accountsbalance['type'] === 'debit') {
                $equity_balance = $this->recalculateUserEquity($accountsbalance)['equity_balance'];

                if ($equity_balance < $accountsbalance['amount']) {
                    $this->addResponse('Amount is greater than Equity Balance, Cannot remove', 1);

                    return false;
                }
            }

            if ($this->remove($data['id'])) {
                $this->recalculateUserEquity($accountsbalance);

                $this->addResponse('Removed Balance.');

                return true;
            }
        }

        $this->addResponse('Error removing balance.', 1);
    }

    public function getUserEquity($data)
    {
        return $this->recalculateUserEquity($data)['equity_balance'];
    }

    public function recalculateUserEquity($data)
    {
        if ($this->config->databasetype === 'db') {
            $conditions =
                [
                    'conditions'    => 'user_id = :user_id:',
                    'bind'          =>
                        [
                            'user_id'       => (int) $data['user_id'],
                        ]
                ];
        } else {
            $conditions =
                [
                    'conditions'    => ['user_id', '=', (int) $data['user_id']]
                ];
        }

        $balancesArr = $this->getByParams($conditions);

        $balanceTotal = 0;
        $balances = [];

        if ($balancesArr && count($balancesArr) > 0) {
            $balancesArr = msort($balancesArr, 'date');

            foreach ($balancesArr as $balanceKey => &$balance) {
                if ($balance['type'] === 'debit') {
                    $balance['balance'] = $balanceTotal = numberFormatPrecision($balanceTotal + $balance['amount']);
                } else if ($balance['type'] === 'credit') {
                    $balance['balance'] = $balanceTotal = numberFormatPrecision($balanceTotal - $balance['amount']);
                }
                // trace([$balance]);
                //Fill the rest of dates with its previous balance.
                if ($balanceKey !== count($balancesArr) - 1) {
                    if ($balance['date'] !== $balancesArr[$balanceKey + 1]['date']) {
                        $balances[$balance['date']] = $balance['balance'];

                        $this->fillBalanceDays($balances, $balance, $balancesArr[$balanceKey + 1]);
                    } else {
                        $balances[$balance['date']] = $balance['balance'];
                    }
                } else {
                    $balances[$balance['date']] = $balance['balance'];
                }
            }

            if (count($balances) > 0) {
                $lastDateOfBalance = \Carbon\Carbon::parse($this->helper->lastKey($balances));
                $today = \Carbon\Carbon::now();
                if ($today->gt($lastDateOfBalance)) {
                    $startEndDates = (\Carbon\CarbonPeriod::between($this->helper->lastKey($balances), $today->toDateString()))->toArray();

                    if (count($startEndDates) >= 2) {
                        foreach ($startEndDates as $startEndDateKey => $startEndDate) {
                            if ($startEndDateKey === 0) {
                                continue;
                            }
                            $balances[$startEndDate->toDateString()] = $this->helper->last($balances);
                        }
                    }
                }
            }
        }

        $accountsUsersModel = new AppsFintechAccountsUsers;

        if ($this->config->databasetype === 'db') {
            $accountsUser = $accountsUsersModel::findFirst(['account_id = ' . (int) $data['user_id']]);
        } else {
            $accountsUsersStore = $this->ff->store($accountsUsersModel->getSource());

            $accountsUser = $accountsUsersStore->findOneBy(['id', '=', (int) $data['user_id']]);
        }

        if ($accountsUser) {
            $accountsUser['equity_balance'] = $balanceTotal;

            if ($this->config->databasetype === 'db') {
                $accountsUsersModel->assign($accountsUser);

                $accountsUsersModel->update();
            } else {
                $accountsUsersStore->update($accountsUser);
            }
        }

        $this->addResponse('Recalculated',
                           0,
                           [
                                'equity_balance' => str_replace('EN_ ',
                                                                '',
                                                                (new \NumberFormatter('en_IN', \NumberFormatter::CURRENCY))
                                                                    ->formatCurrency($accountsUser['equity_balance'], 'en_IN')
                                                                ),
                                'balances' => $balances
                            ]
        );

        return [
            'equity_balance' => $accountsUser['equity_balance'],
            'balances' => $balances
        ];
    }

    protected function fillBalanceDays(&$balances, $balance, $nextTransaction)
    {
        $startEndDates = (\Carbon\CarbonPeriod::between($balance['date'], $nextTransaction['date']))->toArray();

        if (count($startEndDates) > 2) {
            foreach ($startEndDates as $dateKey => $date) {
                if ($dateKey === 0 ||
                    $dateKey === count($startEndDates) - 1
                ) {
                    continue;
                }

                $balances[$date->toDateString()] = $balance['balance'];
            }
        }
    }
}