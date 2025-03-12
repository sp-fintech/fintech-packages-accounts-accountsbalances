<?php

namespace Apps\Fintech\Packages\Accounts\Balances;

use System\Base\BasePackage;

class AccountsBalances extends BasePackage
{
    //protected $modelToUse = ::class;

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
        //
    }

    public function updateAccountsBalances($data)
    {
        $accountsbalances = $this->getById($id);

        if ($accountsbalances) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }

    public function removeAccountsBalances($data)
    {
        $accountsbalances = $this->getById($id);

        if ($accountsbalances) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }
}