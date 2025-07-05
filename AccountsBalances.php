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

        $balances = $this->getByParams($conditions);

        $debitTotal = 0;
        $creditTotal = 0;
        if ($balances && count($balances) > 0) {
            foreach ($balances as $balance) {
                if ($balance['type'] === 'debit') {
                    $debitTotal = $debitTotal + $balance['amount'];
                } else if ($balance['type'] === 'credit') {
                    $creditTotal = $creditTotal + $balance['amount'];
                }
            }
        }

        $equityTotal = $debitTotal - $creditTotal;

        $accountsUsersModel = new AppsFintechAccountsUsers;

        if ($this->config->databasetype === 'db') {
            $accountsUser = $accountsUsersModel::findFirst(['account_id = ' . (int) $data['user_id']]);
        } else {
            $accountsUsersStore = $this->ff->store($accountsUsersModel->getSource());

            $accountsUser = $accountsUsersStore->findOneBy(['id', '=', (int) $data['user_id']]);
        }

        if ($accountsUser) {
            $accountsUser['equity_balance'] = $equityTotal;

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
                                'credit_total' => str_replace('EN_ ',
                                                                '',
                                                                (new \NumberFormatter('en_IN', \NumberFormatter::CURRENCY))
                                                                    ->formatCurrency($creditTotal, 'en_IN')
                                                                ),
                                'debit_total' => str_replace('EN_ ',
                                                                '',
                                                                (new \NumberFormatter('en_IN', \NumberFormatter::CURRENCY))
                                                                    ->formatCurrency($debitTotal, 'en_IN')
                                                                )
                            ]
        );

        return [
            'equity_balance' => $accountsUser['equity_balance'],
            'credit_total' => $creditTotal,
            'debit_total' => $debitTotal
        ];
    }
}