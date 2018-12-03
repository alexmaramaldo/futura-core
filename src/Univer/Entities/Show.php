<?php

namespace Univer\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Univer\Scopes\AvailabilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Show extends Title
{
    public $rules = [];
    public $messages = [];

    protected $appends = array('url');

    //Só retorna Titles do tipo Show
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('type', function(Builder $builder) {
            $builder->where('type', 'show');
        });

    }

    public function put($id, $data)
    {
        $object                    = Show::find($id);
        $object->title             = $data['title'];
        $object->description       = $data['description'];
        $object->status            = $data['status'];
        if(array_key_exists('availability', $data))
            $object->availability      = $data['availability'];
        return $object;
    }

    public function store($data)
    {
        $object                    = new Show();
        $object->title             = $data['title'];
        $object->description       = $data['description'];
        $object->status            = $data['status'];
        if(array_key_exists('availability', $data))
            $object->availability      = $data['availability'];
        return $object;
    }

    public function seasons()
    {
        return $this->hasMany('Univer\Entities\Season', 'title_id');
    }

    public function activeSeasons()
    {
        $seasons = $this
            ->seasons()
            ->where('status', 1)
            ->orderBy('order', 'ASC')
            ->get();
        $list_collect = collect();
        foreach($seasons as $season)
            if($season->videos()->where('status', 1)->count() > 0)
                $list_collect->push($season);
        return $list_collect;
    }

    public function getMediaType()
    {
        return "show";
    }

    public function getPrimaryKey(){
        return parent::getPrimaryKey();
    }

    public function getUrlAttribute()
    {
        $type = $this->getMediaType();
        $url = 'https://watch.tv.br';
        if($type=='movie' || $type=='show' || $type=='video'){
            $url .= '/'.$type.'/'.$this->id.'-'.str_slug($this->title, '-');
        }else{
            if($type=='season'){
                if($this->show()->first()){
                    $show = $this->show()->first();
                    $url .= '/show/'.$this->title_id.'-'.str_slug($show->title, '-').'/'.$type.'/'.$this->id.'-'.str_slug($this->title, '-');
                } else{
                    $url .= '/';
                }
            }
        }

        return $url;
    }
}
