<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class TitleInformation extends Model
{
    protected $table = 'title_information';

    protected $primaryKey = 'item_id';

    protected $hidden =[
        'item_id',
        'item_type',
        'genero',
        'sub_genero',
        'categoria',
        'sub_categoria',
        'temporada',
        'titulo',
        'titulo_original',
        'numero_do_episodio',
        'episodio',
        'pais',
        'lingua',
        'legendas',
        'classificacao',
        'tags',
        'inclusao',
        'vencimento',
        'produtora',
        'distribuidora',
        'restricao',
    ];

    protected $fillable =[
        'item_id',
        'item_type',
        'genero',
        'sub_genero',
        'categoria',
        'sub_categoria',
        'temporada',
        'titulo',
        'titulo_original',
        'numero_do_episodio',
        'episodio',
        'diretor',
        'elenco',
        'atores',
        'ano',
        'duracao',
        'pais',
        'lingua',
        'audio',
        'legendas',
        'classificacao',
        'tags',
        'inclusao',
        'vencimento',
        'sinopse',
        'produtora',
        'distribuidora',
        'restricao',
    ];
    public $timestamps = false;

    public function getColumn($column,$prefix){
        if(strlen($this->$column)>0){
            return "<p><strong>$prefix: </strong>".$this->$column."</p>";
        }
    }

    public function setElencoAttribute($value){
        return $this->attributes['atores'] = $value;
    }

}
