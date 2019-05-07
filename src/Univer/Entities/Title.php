<?php

namespace Univer\Entities;

use Illuminate\Support\Str;
use Univer\Scopes\AvailabilityScope;
use Univer\Traits\hasInfo;
use Univer\Traits\Rentable;
use Illuminate\Support\Facades\DB;
use Univer\Contracts\iMediaInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Title extends Model implements iMediaInterface
{
    protected $rules=[];

    protected $messages =[];

    use Rentable,hasInfo;

    /**
     * @param $data
     * @param bool $edit
     * @return mixed
     */
    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    // Filtra por availability
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new AvailabilityScope());

        $currentUrl = \Request::url();

        // Permite visualização de filmes inativos somente no admin
        static::addGlobalScope('ativos', function(Builder $builder) use($currentUrl){
            if(strpos($currentUrl,'admin') === false){
                $builder->where('v3_titles.status',1);
            }
        });
    }

    protected $table = 'v3_titles';
    protected $primaryKey = 'id';
    protected $fillable = [
        'title',
        'description',
        'type',
        'cover',
        'status',
        'availability'
    ];

    public function delete()
    {
        DB::table('pricing_item')
            ->where('item_id', $this->id)
            ->where('item_type', 'movie')
            ->delete();
        DB::table('v3_titles_categories')
            ->where('title_id', $this->id)
            ->delete();
        DB::table('v3_titles_genres')
            ->where('title_id', $this->id)
            ->delete();
        return parent::delete();
    }

    /**
    * Retorna uma instância da subclasse correspondente.
    * Nesse caso está fazendo uma nova query, 
    * o ideal seria só instanciar uma subclasse com os atributos ja existentes
    * Tentei fazer isso mas ele só criou com os attributos fillable, enfim
    */
    public function getTypeInstance()
    {
        if($this->type=='show'){

            return Show::find($this->id);

        }else if($this->type=='movie'){

            return Movie::find($this->id);
        }
        return false; 
    }

    public function seasons()
    {
        return $this->hasMany('Univer\Entities\Season', 'title_id');
    }

    public function activeSeasons()
    {
        $seasons = $this->hasMany('Univer\Entities\Season', 'title_id')
            ->where('status', 1)
            ->orderBy('order', 'ASC')
            ->get();
        $list_collect = collect();
        foreach($seasons as $season)
            if($season->videos()->where('status', 1)->count() > 0)
                $list_collect->push($season);
        return $list_collect;
    }

    public function videos()
    {
        $videos = collect();

        if($this->getMediaType() === 'movie')
        {
            return Movie::find($this->id)->video();
        }
        else
        {
            foreach($this->seasons()->get() as $season)
            {
                foreach($season->videos()->get() as $video)
                {
                    $videos->push($video);
                }
            }
            return $videos;
        }
    }

    public function getMediaId()
    {
        return $this->id;
    }

    public function getMediaType()
    {
        return $this->type;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function presentName()
    {
        return $this->attributes['title'];
    }

    public function scopeOfCategory($query, $category_ids)
    {
        $category_ids = is_array($category_ids) ? $category_ids : array($category_ids);
        return $query
            ->join('v3_titles_categories', 'v3_titles.id', '=', 'v3_titles_categories.title_id')
            ->leftJoin('pricing_item', function($join)
            {
                $join
                    ->on('pricing_item.item_id', '=', 'v3_titles.id');
            })
            ->whereIn('v3_titles_categories.category_id', $category_ids)
            ->where(function ($query)
            {
                $query
                    ->where(function ($query)
                    {
                        $query
                            ->where('date_from', '<=', Carbon::now())
                            ->where('date_to', '>=', Carbon::now());
                    })->orWhere(function ($query) {
                        $query
                            ->whereNull('date_from')
                            ->whereNull('date_to');
                    });
            });
    }

    public function scopeOfGenre($query, $genre_ids)
    {
        $genre_ids = is_array($genre_ids) ? $genre_ids : array($genre_ids);
        $query = $query->select(DB::raw("v3_titles.id, v3_titles.title, v3_titles.description, v3_titles.type, v3_titles.cover, v3_titles.highlight, v3_titles.availability, v3_titles.updated_at, v3_titles.created_at, pricing_item.item_id, pricing_item.item_type, pricing_item.price, pricing_item.discount, pricing_item.price_type, pricing_item.pricing_expiration_id, pricing_item.date_from, pricing_item.date_to"))
            ->join('v3_titles_genres', 'v3_titles.id', '=', 'v3_titles_genres.title_id')
            ->leftJoin('pricing_item', function($join)
            {
                $join
                    ->on('pricing_item.item_id', '=', 'v3_titles.id');
            })
            ->whereIn('v3_titles_genres.genre_id', $genre_ids)
            ->where(function ($query)
            {
                $query
                    ->where(function ($query)
                    {
                        $query
                            ->where('date_from', '<=', Carbon::now())
                            ->where('date_to', '>=', Carbon::now());
                    })->orWhere(function ($query) {
                        $query
                            ->whereNull('date_from')
                            ->whereNull('date_to');
                    });
            });
            //dd($query->toSql());
        return $query;
    }

    public function scopeOfGenreMK($query, $genre_ids)
    {
        $user = Auth::user();

        $plano = $user
            ->planos()
            ->select('_001_isps_tickets.name as uname',
                '_001_isps_tickets.email as uemail',
                '_001_isps_tickets.id_integracao as uintegracao',
                '_001_isps_packages_tickets.type as plano',
                '_001_isps_packages_tickets.plane as pacote',
                '_001_isps.company as empresa',
                '_001_isps.contact as contato',
                '_001_isps.email as eemail',
                '_001_isps.phone as etelefone')
            ->join('_001_isps_tickets','id_transaction','id_plano')
            ->join('_001_isps_packages_tickets','_001_isps_packages_tickets.id_isps_package_ticket','_001_isps_tickets.id_isps_package_ticket')
            ->join('_001_isps','_001_isps.id_isp','_001_isps_packages_tickets.id_isp')
            ->where('planos.status', 'ativo')
            ->where('_001_isps_packages_tickets.type', Category::find(Genre::find($genre_ids)->id_category)->slug)
            ->first();

        $genre_ids = is_array($genre_ids) ? $genre_ids : array($genre_ids);

        $query = $query->select(DB::raw("v3_titles.id, v3_videos.title, v3_videos.description, 'MARKETPLACE' as type, v3_videos.highlight, v3_videos.availability, v3_videos.updated_at, v3_videos.created_at, case " . (($plano) ? '1' : '0') . " when 1 then concat(v3_videos.legenda" . ((!empty($plano) && $plano->plano == 'espn') ? ", '&mvpd=watchtvbrasil&apikey=77bf60aa-dd56-4d38-8407-dfd52fdce6ee'" : '') . ") else concat('/marketplace/', v3_videos.id) end as myurl, '" . (($plano) ? '_blank' : '_self') . "' as targuet"))
            ->join('v3_titles_genres', 'v3_titles.id', '=', 'v3_titles_genres.title_id')
            ->join('v3_videos_titles', 'v3_titles.id', '=', 'v3_videos_titles.title_id')
            ->join('v3_videos', 'v3_videos_titles.video_id', '=', 'v3_videos.id')
            ->whereIn('v3_titles_genres.genre_id', $genre_ids);
        //dd($query->toSql());
        return $query;
    }

    public function categories()
    {
        return $this->belongsToMany('Univer\Entities\Category', 'v3_titles_categories', 'title_id', 'category_id');
    }

    public function visible()
    {
        if($this->availability == 'TVOD')
        {
            return DB::table('pricing_item')
                ->where('item_id', $this->id)
                ->where(function ($query)
                {
                    $query
                        ->where(function ($query)
                        {
                            $query
                                ->where('date_from', '<=', Carbon::now())
                                ->where('date_to', '>=', Carbon::now());
                        })->orWhere(function ($query) {
                            $query
                                ->whereNull('date_from')
                                ->whereNull('date_to');
                        });
                })->count() > 0;
        }
        else
            return true;
    }

    public function genres()
    {
        return $this->belongsToMany('Univer\Entities\Genre', 'v3_titles_genres', 'title_id', 'genre_id');
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
}
