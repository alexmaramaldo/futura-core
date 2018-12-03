<?php

namespace Univer\Scopes;

use App\Http\Requests\Request;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AvailabilityScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if(\Request::has('t')){
            $availability = \Request::get('t');
            if(strpos($availability,',') != false){
                $availability = explode(',',$availability);
            } else{
                $availability = [$availability];
            }

            for($i = 0; $i<count($availability);$i++){
                $aval = $availability[$i];
                switch ($aval){
                    case 'avulso':
                        $new = 'TVOD';
                        break;
                    case 'misto':
                        $new = 'MIXED';
                        break;
                    case 'assinantes':
                        $new = 'SVOD';
                        break;
                    default:
                        $new = $aval;
                }
                $availability[$i] = $new;
            }
            if(Auth::check() && Auth::user()->isAtivo()){
                $availability = 'SVOD';
            }

            $builder->ofAvailability($availability);
        }
        $url = \Request::url();

        if(strpos($url,'assinantes') !== false){
            $builder->ofAvailability(['SVOD','MIXED']);
        } elseif (strpos($url,'conteudo-avulso') !== false){
            $builder->ofAvailability(['TVOD','MIXED']);
        }
    }
}