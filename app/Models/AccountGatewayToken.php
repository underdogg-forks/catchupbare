<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AccountGatewayToken
 */
class AccountGatewayToken extends Eloquent
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $casts = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payment_methods()
    {
        return $this->hasMany('App\Models\PaymentMethod');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function acc_gateway()
    {
        return $this->belongsTo('App\Models\AccountGateway');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function default_payment_method()
    {
        return $this->hasOne('App\Models\PaymentMethod', 'id', 'default_payment_method_id');
    }

    /**
     * @return mixed
     */
    public function autoBillLater()
    {
        if ($this->default_payment_method) {
            return $this->default_payment_method->requiresDelayedAutoBill();
        }

        return false;
    }

    /**
     * @param $query
     * @param $clientId
     * @param $accGatewayId
     * @return mixed
     */
    public function scopeClientAndGateway($query, $clientId, $accGatewayId)
    {
        $query->where('client_id', '=', $clientId)
            ->where('acc_gateway_id', '=', $accGatewayId);

        return $query;
    }

    /**
     * @return mixed
     */
    public function gatewayName()
    {
        return $this->acc_gateway->gateway->name;
    }

    /**
     * @return bool|string
     */
    public function gatewayLink()
    {
        $accGateway = $this->acc_gateway;

        if ($accGateway->gateway_id == GATEWAY_STRIPE) {
            return "https://dashboard.stripe.com/customers/{$this->token}";
        } elseif ($accGateway->gateway_id == GATEWAY_BRAINTREE) {
            $merchantId = $accGateway->getConfigField('merchantId');
            $testMode = $accGateway->getConfigField('testMode');
            return $testMode ? "https://sandbox.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}" : "https://www.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}";
        } else {
            return false;
        }
    }

}
