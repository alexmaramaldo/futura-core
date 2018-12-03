<?php

namespace Univer\Entities;

use Univer\Scopes\AvailabilityScope;
use Univer\Traits\hasInfo;
use Univer\Traits\Rentable;
use Univer\Contracts\iMediaInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Season extends Model implements iMediaInterface
{
    use Rentable,hasInfo;

    protected $appends = array('url');

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new AvailabilityScope());

        $currentUrl = \Request::url();

        // Permite visualização de filmes inativos somente no admin
        static::addGlobalScope('ativos', function(Builder $builder) use($currentUrl){
            if(strpos($currentUrl,'admin') === false){
                $builder->where('status',1);
            }
        });
    }

    protected $table = 'v3_seasons';
    protected $fillable = [
        'title',
        'title_id',
        'description',
        'cover',
        'ano',
        'order',
        'availability',
        'status'
    ];

    public $rules = [];
    public $messages = [];

    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    public function put($id, $data)
    {
        $object                    = Season::find($id);
        $object->title_id          = $data['show_id'];
        $object->title             = $data['title'];
        $object->description       = $data['description'];
        $object->availability      = $data['availability'];
        $object->ano               = $data['ano'];
        $object->order             = $data['order'];
        $object->status            = $data['status'];
        return $object;
    }

    public function store($data)
    {
        $object                    = new Season();
        $object->title_id          = $data['show_id'];
        $object->title             = $data['title'];
        $object->description       = $data['description'];
        $object->availability      = $data['availability'];
        $object->ano               = $data['ano'];
        $object->order             = $data['order'];
        $object->status            = $data['status'];
        return $object;
    }

    public function delete()
    {
        DB::table('pricing_item')
            ->where('item_id', $this->id)
            ->where('item_type', 'season')
            ->delete();
        return parent::delete();
    }

    public function videos()
    {
        return $this
            ->belongsToMany('Univer\Entities\Video', 'v3_videos_seasons', 'season_id', 'video_id')
            ->withPivot('video_id');
    }

    public function active()
    {
        $videos = $this->videos()->where('status', 1)->get();
        return $videos->count() > 0;
    }

    public function show()
    {
        return $this->belongsTo('Univer\Entities\Show', 'title_id');
    }

    public function getMediaId()
    {
        return $this->id;
    }

    public function getMediaType()
    {
        return "season";
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getHighlightAttribute($value)
    {
        if(strlen($value) > 0) {
            return $value;
        }
        return null;
    }

    public function getCoverAttribute($value)
    {
        if(strlen($value) > 0){
            return $value;
        } else{
            return "";
        }
    }

    public function presentName()
    {
     return $this->attributes['title'];
    }

    public function getOrder(){
        if(strlen($this->order > 0)){
         return 'T'.$this->order;
        }
    }

    public function visible()
    {
        if($this->availability == 'TVOD')
        {
            return DB::table('pricing_item')
                    ->where('item_id', $this->id)
                    ->where(function ($query)
                    {
                        $query->where('date_from', '<=', Carbon::now())
                            ->where('date_to', '>=', Carbon::now());
                    })->orWhere(function ($query) {
                        $query->whereNull('date_from')
                            ->whereNull('date_to');
                    })->count() > 0;
        }
        else
            return true;
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
    public function scopeOfCategory($query, $category_ids)
    {
        $category_ids = is_array($category_ids) ? $category_ids : array($category_ids);
        return $query->join('v3_seasons_categories', 'v3_seasons.id', '=', 'v3_seasons_categories.season_id')->whereIn('v3_seasons_categories.category_id', $category_ids);
    }
    /**
     * Retorna o ID da categoria do conteúdo, ou null
     * @return int|null
     */
    public function getCategoryId()
    {
        $category = $this->categories()->first();
        if($category){
            return $category->id;
        }
        return null;
    }

    public function getCategories()
    {
        return $this->categories()->get();
    }

    public function categories()
    {
        return $this->belongsToMany('Univer\Entities\Category', 'v3_seasons_categories', 'season_id', 'category_id');
    }

    public function pricing_items()
    {
        return $this->hasMany('Univer\Entities\PricingItem', 'item_id', 'id');
    }

}
