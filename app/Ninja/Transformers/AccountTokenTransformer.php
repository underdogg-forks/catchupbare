<?php namespace App\Ninja\Transformers;

use App\Models\AccountToken;
use League\Fractal\TransformerAbstract;

/**
 * Class AccountTokenTransformer
 */
class AccountTokenTransformer extends TransformerAbstract
{

    /**
     * @param AccountToken $acc_token
     * @return array
     */
    public function transform(AccountToken $acc_token)
    {
        return [
            'name' => $acc_token->name,
            'token' => $acc_token->token
        ];
    }
}