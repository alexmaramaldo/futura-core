<?php

namespace Univer\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Univer\Scopes\AvailabilityScope;

class Movie extends Title
{

    protected $appends = array('url');
    
    //Só retorna Titles do tipo Movie
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('type', function(Builder $builder){
            $builder->where('type', 'movie');
        });
    }

    public function videos()
    {
        return $this->belongsToMany('Univer\Entities\Video', 'v3_videos_titles', 'title_id', 'video_id');
    }

    public function video()
    {
        return $this->videos()->first();
    }

    public function presentName()
    {
     return $this->attributes['title'];
    }

    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = 'movie';
    }

    public function getUrlAttribute()
    {
        $type = $this->type;
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
