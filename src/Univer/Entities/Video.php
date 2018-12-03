<?php

namespace Univer\Entities;

use Univer\Traits\hasInfo;
use Univer\Traits\Rentable;
use Univer\Scopes\AvailabilityScope;
use Univer\Contracts\iMediaInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Video extends Model implements iMediaInterface
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
    protected $table = 'v3_videos';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_sambavideos',
        'id_project',
        'id_georegra',
        'title',
        'description',
        'legenda',
        'cover',
        'highlight',
        'highlight2',
        'background',
        'duracao',
        'order',
        'availability',
        'status'
    ];

    public $rules = [];
    public $messages = [];
    protected static $sortableField = 'position';

    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    public function put($id, $data)
    {
        $object                     = Video::find($id);
        $object->id_sambavideos     = $data['id_sambavideos'];
        $object->id_georegra        = $data['id_georegra'];
        $object->title              = $data['title'];
        $object->description        = $data['description'];
        //$object->highlight          = $data['highlight'];
        //$object->highlight2         = $data['highlight2'];
        //$object->background         = $data['background'];
        //$object->duracao            = $data['duracao'];
        $object->id_project         = 4307;
        $object->status             = $data['status'];
        return $object;
    }

    public function store($data)
    {
        $object                     = new Video();
        $object->id_sambavideos     = $data['id_sambavideos'];
        $object->id_georegra        = $data['id_georegra'];
        $object->title              = $data['title'];
        $object->description        = $data['description'];
        //$object->highlight          = $data['highlight'];
        //$object->highlight2         = $data['highlight2'];
        //$object->background         = $data['background'];
        //$object->duracao            = $data['duracao'];
        $object->id_project         = 4307;
        $object->status             = $data['status'];
        return $object;
    }


    //Regra de restrição de conteúdo por localização
    public function georegra()
    {
       return $this->belongsTo('App\V2Georegra', 'id_georegra');
    }

    /**
     *
     * @return mixed
     */
    public function show(){
        $season = $this->season()->first();

        if($season){
            $show = $season->show()->first();
            if($show){
                return $show;
            }
        } else{
            return $this->title()->first();
        }
        return null;
    }

    public function tags()
    {
        return $this->belongsToMany('Univer\Entities\Tag', 'v3_tags_videos', 'video_id', 'tag_id');
    }

    //Video pode pertencer a uma temporada de um show (title do tipo 'show')
    public function season()
    {
        return $this->belongsToMany('Univer\Entities\Season', 'v3_videos_seasons', 'video_id', 'season_id');
    }

    public function activeSeasons()
    {
        $seasons = $this
            ->season()
            ->where('status', 1)
            ->orderBy('order', 'ASC')
            ->get();
        $list_collect = collect();
        foreach($seasons as $season)
            if($season->videos()->where('status', 1)->count() > 0)
                $list_collect->push($season);
        return $list_collect;
    }

    public function videoseason()
    {
        return $this->belongsToMany('App\VideoSeason');
    }

    /**
     * Video pode pertencer a um title diretamente (title do tipo 'movie')
     *
     */
    public function title()
    {
        return $this->belongsToMany('Univer\Entities\Movie', 'v3_videos_titles', 'video_id', 'title_id');
    }

    public function getMediaId()
    {
        return $this->id;
    }

    public function getMediaType()
    {
        return "video";
    }

    public function getPrimaryKey()
    {
     return 'id';
    }

    public function presentName()
    {
        if(!is_null($this->season()->first())){
            return $this->season()->first()->title.' '.$this->attributes['title'];
        }
        return $this->attributes['title'];
    }


    // Video não tem cover.
    // Retorna cover do seu show (filme, serie ou season) ou uma capa padrão
    public function getCoverAttribute($value)
    {
        return $this->show() ? $this->show()->cover : "";
    }

    public function getHighlightAttribute($value)
    {
        if($value && strlen($value)> 0){
            return $value;
        }
        return '';
    }

    public function getHighlight2Attribute($value)
    {
        if($value && strlen($value)> 0){
            return $value;
        }
        return '';
    }

    public function getBackgroundAttribute($value)
    {
        if($value && strlen($value)> 0){
            return $value;
        }
        return '';
    }

    /**
     * @return string
     */
    public function getOrder(){
        if(strlen($this->order > 0)){
            return 'E'.$this->order;
        }
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
