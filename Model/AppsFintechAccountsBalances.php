<?php

namespace Apps\Fintech\Packages\Accounts\Balances\Model;

use System\Base\BaseModel;

class AppsFintechAccountsBalances extends BaseModel
{
    public $id;

    public $account_id;

    public $user_id;

    public $date;

    public $amount;

    public $type;//Debit/Credit/DebitInTransit/CreditInTransit

    public $details;
}