<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $table = 'v3_favorites';
    protected $primaryKey = 'id';
    protected $dates =['created_at','updated_at'];
    protected $fillable = [
        'perfil_id',
        'item_id',
        'item_type',
        'stars',
        'created_at',
        'updated_at',
        'status'
    ];

    public function serie(){
        return $this->hasOne(Show::class,'id','item_id');
    }

    public function title(){
        try{
            return getMediaInstance($this->item_id,$this->item_type);
        } Catch(\Exception $ex){
            \Log::error("Falha ao localizar entidade relacionada ao favorito",[
                'id' => $this->id,
                'item_id'=>$this->item_id,
                'item_type'=>$this->item_type
            ]);
        }
    }
}
