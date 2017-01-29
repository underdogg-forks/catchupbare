<?php namespace App\Ninja\Repositories;

use DB;
use Session;
use App\Models\Token;

class TokenRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\AccountToken';
    }

    public function find($userId)
    {
        $query = DB::table('acc_tokens')
                  ->where('acc_tokens.user_id', '=', $userId)
                  ->whereNull('acc_tokens.deleted_at');;

        return $query->select('acc_tokens.public_id', 'acc_tokens.name', 'acc_tokens.token', 'acc_tokens.public_id', 'acc_tokens.deleted_at');
    }
}
