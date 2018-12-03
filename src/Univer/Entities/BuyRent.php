<?php

namespace Univer\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class BuyRent extends Model
{
    protected $table = 'buy_rent';
    protected $fillable = [
        'user_id',
        'item_id',
        'item_type',
        'payment_method',
        'expire_at',
        'start_at',
        'apple_pay_receipt',
        'rental_type',
        'status'
    ];

    public function transaction()
    {
        return $this->hasMany('Univer\Entities\Transaction');
    }

    public function items(){
        return $this->hasMany('Univer\Entities\BuyRentItems');
    }

    /**
     * Verifica a data de expiração de uma compra, baseado na existencia
     * de start date.
     *
     * @param bool $append
     * @return string
     */
    public function getExpirationDate($append = false, $numeric = false)
    {
        if($this->rental_type === 'rent')
        {
            $date = $this->getExpirationDatetime();
            $expireDateHoras = $date->diffInHours();
            $expireDateDays = $date->diffInDays();
            if((int)$expireDateHoras > 0 || $expireDateDays > 0 )
            {
                if($numeric === true)
                {
                    return (int)$expireDateHoras;
                }
                if($expireDateDays > 0)
                    return "<span>$expireDateDays</span> ".($expireDateDays > 1 ? 'dias' : 'dia');
                elseif($expireDateHoras > 0)
                    return "<span>$expireDateHoras</span> ".($expireDateHoras > 1 ? 'horas' : 'hora');
            }
            return "<span>hoje</span>";
        }
    }

    public function getFriendlyStatusAttribute()
    {
     switch ($this->status){
         case 'active':
             return '';
         break;
         case 'pending':
             return '(pendente)';
         break;
         case 'rejected':
             return '(rejeitado)';
         break;
         default:
             return '';
         break;
     }
    }

    /**
     * @return Carbon
     */
    protected function getExpirationDatetime()
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $this->expiration_date);
    }
}
