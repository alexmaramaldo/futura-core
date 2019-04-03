<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 17/10/16
 * Time: 16:02
 */

namespace Univer\Services;

use App\Assistido;
use App\VideoSeason;
use App\Univer\Services\AccessControlService;

use Carbon\Carbon;
use App\Library\Util;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Facades\Agent;
use Univer\Entities\Genre;
use Univer\Entities\Title;
use Univer\Entities\Show;
use Univer\Entities\Season;
use Univer\Entities\Movie;
use Univer\Entities\Video;
use Univer\Entities\Category;
use Univer\Entities\Favorite;
use Illuminate\Support\Facades\Auth;
use Univer\Contracts\iMediaInterface;
use Config;

use Tymon\JWTAuth\JWTAuth;

use Kaltura\Client\Client as KalturaClient;
use Kaltura\Client\Enum\SessionType as KalturaSessionType;

class  ContentService
{
    /**
     * @var Video
     */
    protected $video;

    /**
     * @var Show
     */
    protected $show;

    /**
     * @var Season
     */
    protected $season;

    public function __construct(Video $video,Show $show, Season $season)
    {
        $this->video = $video;

        $this->show = $show;

        $this->season = $season;

        $this->clearCache('components.sub-menu_assinantes');
        $this->clearCache('components.sub-menu_avulso');

    }

    private function clearCache($string)
    {
        if(\Request::get('clearcache')){
            \Cache::forget($string);
        }
    }

    public function getShow($idShow)
    {
        return $this->show->findOrFail($idShow);
    }


    public function getTitleByVideo($idVideo)
    {
        try{
            $video = Video::find($idVideo);
            $title = $video->show();

            return $title;

        } catch ( \Exception $e ) {
            throw $e;
        }

    }

    /**
     * @param iMediaInterface $media
     * @return \stdClass objeto com atributo status e msg
     */
    public function authorize(iMediaInterface $media, $profile)
    {
        $obj = new \stdClass();
        $obj->status = false;
        $obj->msg = "Unauthorized";
        $obj->ks = "";
        $obj->ip = AccessControlService::getClientIP();
        if(!Auth::check())
        {
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch(\Exception $ex) {}
        }
        else
            $user = Auth::user();
        if(!Auth::check() || !\ClerkService::userCanWatchContent($user->id, $media))
            return $obj;
        $media = $this->getMedia($media);
        $kalturaConfig = Config::get('kaltura');
        $config = new \Kaltura\Client\Configuration($kalturaConfig->api->partnerId);
        $config->setServiceUrl('https://www.kaltura.com');
        $client = new KalturaClient($config);
        $ks = \App\Http\Controllers\Kaltura::ksAdmin();
        $client->setKs($ks);
        define("USERNAME", gethostname()."_serer_".$user->id."_user_".$profile."_profile");
        define("PASSWORD",  '99$99kal');
        define("EMAIL", gethostname()."_".$profile."_".$user->email);
        try
        {
            $client->user->get(USERNAME);
        }
        catch (\Exception $ex)
        {
            $adduser = new \Kaltura\Client\Type\User();
            $adduser->id = USERNAME;
            $adduser->password = PASSWORD;
            $adduser->country = "BR";
            $adduser->email = EMAIL;
            $client->user->add($adduser);
        }
        $obj->status = true;
        $obj->msg = "Authorized";

        /*if(filter_var($obj->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        {
            $partsip = explode(":", $obj->ip);
            unset($partsip[count($partsip)-1]);
            $obj->ip = join(":", $partsip);
        }*/

        $iprestrict = "";
        //if(!filter_var($obj->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        //    $iprestrict = ",iprestrict:".$obj->ip;

        $obj->ks = $client->session->start($kalturaConfig->api->adminSecret, USERNAME, KalturaSessionType::USER, $kalturaConfig->api->partnerId , 20, "sview:".$media->id_sambavideos.$iprestrict);

        $obj->ks = "";

        return $obj;
    }

    /**
     * Verifica se usuário pode ver conteúdo
     * @param iMediaInterface $media
     * @return bool
     * @throws \Exception
     */
    public function checkGeolocationLock(iMediaInterface $media)
    {
        $idGeoregra = $media->id_georegra;
        if(!$idGeoregra){
            return true;
        }

        try{
            if(\GeoIP::userIsAllowed($idGeoregra)){
                return true;
            } else{
                throw new \Exception("Geolocation check failed.");
            }

        } catch(\Exception $ex){
            throw $ex;
        }
    }

    /**
     * Cria um show
     * @param $array
     */
    public function createShow($array){

        return $this->show = Show::create([
            'title'=>$array['title'],
            'description'=> $array['description'],
        ]);

    }

    /**
     * @param Carbon|null $timeLimit
     * @param int $limit
     * @param bool $fillWithTitle Define se o método deve retornar a série de um determinado vídeo ou não
     * @return mixed
     *
     * Retorna uma collection com o conteúdo mais assistido de todos os tempos ou à partir da data limite $timeLimit,
     * limitado por $limit ou pelo padrão 24
     *
     */
    public function getMostWatched(Carbon $timeLimit = null, $limit = 30, $fillWithTitle = true)
    {
        //Condicional para marcar no cache se a consulta é limitada por tempo ou não pois o parâmetro time limit muda a cada requisição
        $cacheTimeLimit = !is_null($timeLimit) ? '_time_limited_' : '_';
        $cacheFillTitle = !is_null($fillWithTitle) ? '_with_title_' : '_without_title_';
        $this->clearCache('mais_assistido'.$cacheTimeLimit.'limit_'.$limit.'_'.$cacheFillTitle);
        return \Cache::remember('mais_assistido'.$cacheTimeLimit.'limit_'.$limit.'_'.$cacheFillTitle, 30, function() use ($timeLimit, $limit, $fillWithTitle)
        {
            $query = Assistido::select('id_sambavideos', \DB::raw('count(id_sambavideos) as total'));
            if(!empty($timeLimit))
                $query = $query->where('updated_at', '>=', $timeLimit);
            $list = $query->groupBy('id_sambavideos')
                ->orderBy('total', 'desc');
            if($limit > 0)
                $list->limit($limit);
            $list = $list->get();
            $list_collect = collect();
            foreach($list as $item)
            {
                $video = Video::where('id_sambavideos', $item->id_sambavideos)->first();
                if(count($video) > 0)
                {
                    if($fillWithTitle)
                    {
                        $show = $video->show();
                        if($show)
                            if($show->visible())
                                $list_collect->push($show);
                    }
                    else
                        if($video->visible())
                            $list_collect->push($video);
                }
            }
            $list_collect = $list_collect->filter()->unique(function($item)
            {
                return $item->id;
            });
            return $list_collect->unique();
        });
    }

    /**
     * @param Carbon|null $timeLimit
     * @param int $limit
     * @param bool $fillWithShow
     * @return mixed
     */
    public function getMostWatchedVideos(Carbon $timeLimit = null, $limit = 24, $fillWithShow = false)
    {
        return ($this->getMostWatched($timeLimit,$limit,$fillWithShow));
    }

    /**
     * @param int $id_perfil
     * @return mixed
     *
     * Retorna um movie aleatório assistido pelo usuário de perfil $id_perfil
     *
     */
    public function getRandomWatchedMovie($id_perfil)
    {
        $this->clearCache('random_watched_movie_'.$id_perfil);
        //return \Cache::remember('random_watched_movie_'.$id_perfil, 30, function() use ($id_perfil)
        //{
        $assistido = Assistido::select('assistido.id_sambavideos')
            ->whereRaw('assistido.posicao+600 > assistido.duracao')
            ->where('id_perfis', $id_perfil)
            ->where('v3_titles.status', 1)
            ->join('v3_videos', 'v3_videos.id_sambavideos', 'assistido.id_sambavideos')
            ->leftJoin('v3_videos_titles', 'v3_videos_titles.video_id', 'v3_videos.id')
            ->leftJoin('v3_videos_seasons', 'v3_videos_seasons.video_id', 'v3_videos.id')
            ->leftJoin('v3_seasons', 'v3_seasons.id', 'v3_videos_seasons.season_id')
            ->leftJoin('v3_titles', function($join)
            {
                $join->on('v3_titles.id', '=', 'v3_videos_titles.title_id')
                    ->orWhere('v3_titles.id', '=', 'v3_seasons.title_id');
            })
            ->leftJoin('pricing_item', function($join)
            {
                $join
                    ->on('pricing_item.item_id', '=', 'v3_titles.id')
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
            })
            ->with('episodio')
            ->inRandomOrder()
            ->first();
        if($assistido)
            return $assistido->episodio;
        else
            return null;
        //});
    }

    /**
     * @param int $id_perfil
     * @return mixed
     *
     * Retorna um show aleatório assistido pelo usuário de perfil $id_perfil
     *
     */
    public function getRandomWatchedShow($id_perfil)
    {
        $this->clearCache('random_watched_show_'.$id_perfil);
        $assistido = \Cache::remember('random_watched_show_'.$id_perfil, 30, function() use ($id_perfil){
            return Assistido::select('id_sambavideos')
                ->whereRaw('posicao >= 0.75 * duracao')
                ->where('id_perfis', $id_perfil)
                ->whereHas('episodio.season')
                ->with('episodio')
                ->inRandomOrder()
                ->first();
        });

        if(!count($assistido))
            return [];

        return $assistido->episodio;

    }

    /**
     * @param $movie
     * @param $type
     * @param int $min
     * @param int $max
     * @return array
     *
     * Retorna o conteúdo relacionado ao vídeo informado com base nas tags e no título, limitado por $min e $max
     *
     */
    public function getRelatedContentByTitleOrTags($movie, $type, $min=6, $max=24)
    {

        if(!in_array($type, ['show', 'movie']))
            return [];

        $cacheKey = 'cs_get_related_content_by_title_or_tags_'.$movie->id.'_'.$type.'_'.$min.'_'.$max;
        $this->clearCache($cacheKey);
        //return \Cache::remember($cacheKey, 1440, function() use ($movie, $type, $min, $max)
        //{

        if ($type == 'movie') {
            $has = 'title';
        } else {
            $has = 'season';
        }

        $related = collect();
        $titulo = Util::removeStopWords($movie->title);
        $words = explode(' ', $titulo);

        //Obtem os conteúdos com base no título
        //$contents = \Cache::remember('videos_relacionados_por_titulo_' . $movie->id . '_max_' . $max, 1440, function () use ($movie, $has, $words, $max) {
        $contents = Video::where('status', 1)
            ->where('id', '!=', $movie->id)
            ->whereHas($has)
            ->where(function ($query) use ($words) {
                for ($i = 0; $i < count($words); $i++) {
                    if (strlen($words[$i]) > 2) {
                        $query->orWhere('title', 'like', '%' . $words[$i] . '%');
                    }
                }
            })->limit($max)->get();
        //});

        foreach ($contents as $content)
            $related->push($content);

        //Obtém os conteúdos com base nas tags
        foreach ($movie->tags()->get() as $tag)
        {
            $videos = $tag->videos()->get();
            foreach ($videos as $video)
            {
                if ($type == 'movie' && $video->title()->count() > 0 && $video->title !== $movie->title) {
                    $related->push($video);
                } elseif ($type == 'show' && $video->season()->count() > 0 && $video->title !== $movie->title) {
                    $related->push($video);
                }
            }
        }

        //Se os conteúdos são de shows, retorna o show.
        if ($type === 'show')
        {
            $related->transform(function ($item, $key)
            {
                if ($show = $item->show())
                    return $show;
            });
        }

        //Se os conteúdos são de filmes, retorna o title.
        if ($type === 'movie') {
            $related->transform(function ($item, $key) {
                //if ($title = $item->title()->where('availability', '=', 'SVOD')->first()) {
                if ($title = $item->title()->first()) {
                    return $title;
                }
            });

        }

        $related = $related->filter()->unique(function ($item) {
            return $item->id;
        });

        $related->shuffle()->slice(0, $max);

        if (count($related) < $min)
            return [];

        return $related;

        //});
    }

    public function getWatchAgain($id_perfil, $limit = 24)
    {
        $cacheKey = 'cs_get_watch_again_'.$id_perfil;
        //$this->clearCache($cacheKey);
        //return \Cache::remember($cacheKey, 240, function() use ($id_perfil)
        //{
        $list = Assistido::select('assistido.id_sambavideos', 'assistido.posicao', 'assistido.duracao', 'assistido.id_perfis')
            ->whereRaw('assistido.posicao+600 > assistido.duracao')
            ->where('id_perfis', $id_perfil)
            ->where('v3_titles.status', 1)
            ->join('v3_videos', 'v3_videos.id_sambavideos', 'assistido.id_sambavideos')
            ->leftJoin('v3_videos_titles', 'v3_videos_titles.video_id', 'v3_videos.id')
            ->leftJoin('v3_videos_seasons', 'v3_videos_seasons.video_id', 'v3_videos.id')
            ->leftJoin('v3_seasons', 'v3_seasons.id', 'v3_videos_seasons.season_id')
            ->leftJoin('v3_titles', function($join)
            {
                $join->on('v3_titles.id', '=', 'v3_videos_titles.title_id')
                    ->orWhere('v3_titles.id', '=', 'v3_seasons.title_id');
            })
            ->leftJoin('pricing_item', function($join)
            {
                $join
                    ->on('pricing_item.item_id', '=', 'v3_titles.id')
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
            })
            ->with('episodio')
            ->orderBy('assistido.updated_at', 'desc')
            ->limit($limit)
            ->get();

        $list->transform(function($item)
        {
            if($item->episodio) {
                $video = $item->episodio;
                if ($video->description == "") {
                    $title = $video->title()->first();
                    if($title)
                        $video->description = $video->title()->first()->description;
                }
                return $item;
            }
        });
        $list_collect = collect();
        foreach($list as $item)
            $list_collect->push($item->episodio);
        return $list_collect->filter();
        //});
    }

    public function getContinueWatching($id_perfil, $limit = 24)
    {
        //$this->clearCache('content_service_get_continue_watching_'.$id_perfil);
        //return \Cache::remember('content_service_get_continue_watching_'.$id_perfil, 10, function() use ($id_perfil, $limit) {
        $list = Assistido::select('assistido.id_sambavideos', 'assistido.posicao', 'assistido.duracao', 'assistido.id_perfis')
            ->whereRaw('assistido.posicao+600 < assistido.duracao')
            ->where('id_perfis', $id_perfil)
            ->where('v3_titles.status', 1)
            ->join('v3_videos', 'v3_videos.id_sambavideos', 'assistido.id_sambavideos')
            ->leftJoin('v3_videos_titles', 'v3_videos_titles.video_id', 'v3_videos.id')
            ->leftJoin('v3_videos_seasons', 'v3_videos_seasons.video_id', 'v3_videos.id')
            ->leftJoin('v3_seasons', 'v3_seasons.id', 'v3_videos_seasons.season_id')
            ->leftJoin('v3_titles', function($join)
            {
                $join->on('v3_titles.id', '=', 'v3_videos_titles.title_id')
                    ->orWhere('v3_titles.id', '=', 'v3_seasons.title_id');
            })
            ->leftJoin('pricing_item', function($join)
            {
                $join
                    ->on('pricing_item.item_id', '=', 'v3_titles.id')
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
            })
            ->with('episodio')
            ->orderBy('assistido.updated_at', 'desc')
            ->limit($limit)
            ->get();

        $list->transform(function($item)
        {
            if($item->episodio) {
                if ($item->posicao > 0)
                    $item->episodio->percent = floor(($item->posicao / $item->duracao) * 100);
                else
                    $item->episodio->percent = 0;
                $video = $item->episodio;
                if ($video->description == "") {
                    $title = $video->title()->first();
                    if($title)
                        $video->description = $video->title()->first()->description;
                }
                return $item;
            }
        });
        $list_collect = collect();
        foreach($list as $item)
            $list_collect->push($item->episodio);
        return $list_collect->filter();
        //});
    }

    public function getVideosFromShow($show_id)
    {
        $title = Title::find($show_id);

        if($title){
            return $title->videos();
        }
    }

    /**
     * @param $category
     * @param bool $fillWithSeason Determina se é para retornar carrosel com foto da temporada, ou da série
     * @param bool $shuffled
     * @return mixed
     */
    public function getShowsOfCategory($category, $fillWithSeason=false, $shuffled=false)
    {
        $fill_cache = $fillWithSeason ? 'seasons' : 'show';
        $shuffle_cache = $shuffled ? 'shuffled' : 'ordered';

        $this->clearCache('cs_get_shows_of_category_'.$category.'_'.$fill_cache.'_'.$shuffle_cache);
        return \Cache::remember('cs_get_shows_of_category_'.$category.'_'.$fill_cache.'_'.$shuffle_cache, 1440, function() use($category, $fill_cache, $shuffle_cache) {
            $query = \Univer\Entities\Show::ofCategory($category)->orderBy('created_at','DESC');

            $shows = $query->get();

            if($fill_cache === 'seasons'){
                $seasons = collect([]);
                $shows->each(function($item,$key) use($seasons){
                    if($showSeasons = $this->getSeasonsOfShow($item)){
                        $showSeasons->each(function($season,$key) use($seasons){
                            $seasons->push($season);
                        });

                    }
                });

                $shows = $seasons->sortByDesc('created_at');
            }


            if($shuffle_cache === 'shuffled'){
                $shows = $shows->shuffle();
            }

            return $shows;
        });
    }

    public function getTitlesOfCategory($category, $fillWithSeason=false, $shuffled=false)
    {
        $fill_cache = $fillWithSeason ? 'seasons' : 'show';
        $shuffle_cache = $shuffled ? 'shuffled' : 'ordered';

        $this->clearCache('cs_get_titles_of_category_'.$category.'_'.$fill_cache.'_'.$shuffle_cache);
        return \Cache::remember('cs_get_titles_of_category_'.$category.'_'.$fill_cache.'_'.$shuffle_cache, 1440, function() use($category, $fill_cache, $shuffle_cache) {
            $query = \Univer\Entities\Title::ofCategory($category)->orderBy('created_at','DESC');

            $titles = $query->get();

            if($fill_cache === 'seasons'){
                $seasons = collect([]);
                $titles->each(function($item,$key) use($seasons){
                    if($showSeasons = $this->getSeasonsOfShow($item)){
                        $showSeasons->each(function($season,$key) use($seasons){
                            $seasons->push($season);
                        });

                    }
                });

                $shows = $seasons->sortByDesc('created_at');
            }


            if($shuffle_cache === 'shuffled'){
                $shows = $titles->shuffle();
            }

            return $titles;
        });
    }

    public function getTitlesOfGener($genero, $limit=24, $availability=["SVOD", "TVOD"])
    {
        //$this->clearCache('content_service_get_movies__gener_'.$genero.'_limit_'.$limit."_availability_".join("_", $availability));
        $movies = \Cache::remember('content_service_get_movies__gener_'.$genero.'_limit_'.$limit."_availability_".join("_", $availability), 1440, function() use ($genero, $limit, $availability)
        {
            $titles = DB::table('v3_titles_genres')
                ->where('genre_id', $genero)
                ->join('v3_titles', 'v3_titles.id', 'v3_titles_genres.title_id')
                ->where("status", 1)
                ->whereIn("availability", $availability)
                ->orderBy('created_at','DESC')
                //->limit($limit)
                ->get();
            $list_collect = collect();
            foreach ($titles as $item)
                $list_collect->push(Movie::find($item->id));
            return $list_collect;
        });
        return $movies;
    }

    /**
     * @param Show $show
     * @return mixed
     */
    public function getSeasonsOfShow(Show $show)
    {
        $this->clearCache('cs_show'.$show->id.'_seasons');
        return \Cache::remember('cs_show'.$show->id.'_seasons',1440,function() use($show){
            return $show->seasons()->get();
        });
    }

    public function getSeasonsOfTitles($titlesIds=array())
    {
        $titles_cache = md5(serialize($titlesIds));
        $this->clearCache('cs_get_seasons_of_titles_'.$titles_cache);
        return \Cache::remember('cs_get_seasons_of_titles_'.$titles_cache, 1440, function() use ($titlesIds) {
            return \Univer\Entities\Season::whereIn('title_id', $titlesIds)->orderBy('created_at','DESC')->get();
        });
    }

    public function getSeasonsOfCategory($categoryId, $shuffled=false)
    {
        $shuffle_cache = $shuffled ? 'shuffled' : 'ordered';

        $query = \Univer\Entities\Season::ofCategory($categoryId)->orderBy('created_at','DESC');

        $seasons = $query->get();

        $seasons->type = "season";

        return $seasons;
    }

    public function getSeasonsAndTitlesOfCategory($categoryId)
    {
        $carousel = collect();

        $seasons = $this->getSeasonsOfCategory($categoryId);
        $titles = $this->getTitlesOfCategory($categoryId);

        $seasons->transform(function($item) use ($carousel){
            $carousel->push($item);
        });

        $titles->transform(function($item) use ($carousel){
            $carousel->push($item);
        });

        return $carousel;
    }

    public function getMyList($id_perfil, $limit = 24)
    {
        $list = Favorite::select('item_id', 'item_type')
            ->where('perfil_id', $id_perfil)
            ->where('status', 1)
            ->where("item_type", "movie")
            ->orWhere("item_type", "show")
            ->where('perfil_id', $id_perfil)
            ->where('status', 1)
            ->limit($limit)
            ->get();
        $list_collect = collect();
        foreach($list as $item) {
            try {
                $item = getMediaInstance($item->item_id, $item->item_type);
                $list_collect->push($item);
            } catch (\Exception $ex) {}
        }
        return $list_collect;
    }

    public function getMovies($limit = 60)
    {
        $this->clearCache('content_service_get_movies_limit_'.$limit);
        $movies = \Cache::remember('content_service_get_movies_limit_'.$limit, 1440, function() use ($limit)
        {
            $movies = Movie::inRandomOrder();
            if($limit > 0)
                $movies->limit($limit);
            $movies = $movies->get()->filter(function ($movie)
            {
                return $movie->visible();
            });
            return $movies;
        });
        return $movies;
    }

    public function getSeasons($limit = 24, $returnids = false)
    {
        $this->clearCache('content_service_get_sessons_limit_' . $limit . json_encode($returnids));
        $shows = \Cache::remember('content_service_get_sessons_limit_' . $limit . json_encode($returnids), 1440, function () use ($limit, $returnids)
        {
            $showsid = DB::table('v3_titles')->select('v3_titles.id')
                ->join('v3_seasons', 'v3_titles.id', '=', 'v3_seasons.title_id')
                ->leftJoin('pricing_item', 'v3_titles.id', '=', 'pricing_item.item_id')
                ->join('v3_videos_seasons', 'v3_seasons.id', '=', 'v3_videos_seasons.season_id')
                ->join('v3_videos', 'v3_videos.id', '=', 'v3_videos_seasons.video_id')
                ->where('v3_titles.status', '=', 1)
                ->where('v3_seasons.availability', '!=', 'EXTERNAL')
                ->where('v3_seasons.status', '=', 1)
                ->where('v3_videos.status', '=', 1)
                ->where(function ($query)
                {
                    $query->where('date_from', '<=', Carbon::now())
                        ->where('date_to', '>=', Carbon::now());
                })->orWhere(function ($query) {
                    $query->whereNull('date_from')
                        ->whereNull('date_to');
                })
                ->distinct()
                ->get();
            if ($showsid->count() > 0)
            {
                if($returnids)
                    return $showsid->toArray();
                $ids = [];
                foreach ($showsid->toArray() as $item)
                    $ids[count($ids)] = $item->id;
                $shows = Show::whereIn('id', $ids)
                    ->where('status', 1)
                    ->where('availability', '!=', 'EXTERNAL')
                    ->orderBy('created_at', 'DESC');
                //if ($limit > 0)
                //    $shows->limit($limit);
                return $shows->get();
            } else
                return null;
        });
        return $shows;
    }

    public function getAllSeasons($limit = 24)
    {
        $seasons = collect([]);

        $this->clearCache('content_service_get_all_sessons_limit_' . $limit);
        $shows = \Cache::remember('content_service_get_all_sessons_limit_' . $limit, 1440, function () use ($limit, $seasons)
        {
            $shows = \ContentService::getSeasons();
            if ($shows->count() > 0)
            {
                $ctlimit = 0;
                for($i = 0; $i < count($shows); $i++)
                {
                    $sessiontitle = $shows[$i]->seasons()->where("status", 1)->where('.availability', '!=', 'EXTERNAL')->get();
                    foreach ($sessiontitle as $item) {
                        if ($ctlimit < $limit) {
                            $session = clone $shows[$i];
                            $session->myurl = '/show/'.$session->id.'-'.str_slug($session->title).'/season/'.$item->id.'-'.str_slug($item->title);
                            $session->id_title = $session->id;
                            $session->id_seasson = $item->id;
                            $session->title = $session->title.' - '.$item->title;
                            $session->cover = $item->cover;
                            $session->highlight = $item->highlight;
                            $session->rand = rand(1,100);
                            $session->created_at = $item->created_at;
                            $session->updated_at = $item->updated_at;
                            $seasons->push($session);
                        }
                        $ctlimit ++;
                    }
                }
                return $seasons->sortBy('rand');
            } else
                return null;
        });
        return $shows;
    }

    public function getAllSeasonsByGenre($limit = 24, $genre)
    {
        $seasons = collect([]);
        //$this->clearCache('content_service_get_all_sessons_genre_limit_' . $genre . $limit);
        $shows = \Cache::remember('content_service_get_all_sessons_genre_limit_' . $genre . $limit, 1440, function () use ($limit, $genre, $seasons)
        {
            $shows = Show::where("availability", "!=", "EXTERNAL")->where("status", 1)->get();
            if ($shows->count() > 0)
            {
                $ctlimit = 0;
                for($i = 0; $i < count($shows); $i++)
                {
                    $sessiontitle = $shows[$i]->seasons()->where("status", 1)->where('.availability', '!=', 'EXTERNAL')->get();

                    foreach ($sessiontitle as $item) {
                        $title_genre = DB::table("v3_titles_genres")
                            ->where('title_id', $item->title_id)
                            ->where('genre_id', $genre)
                            ->first();
                        if ($title_genre && $ctlimit < $limit) {
                            $session = clone $shows[$i];
                            $session->myurl = '/show/' . $session->id . '-' . str_slug($session->title) . '/season/' . $item->id . '-' . str_slug($item->title);
                            $session->id_title = $session->id;
                            $session->id_seasson = $item->id;
                            $session->title = $session->title . ' - ' . $item->title;
                            $session->cover = $item->cover;
                            $session->highlight = $item->highlight;
                            $session->rand = rand(1, 100);
                            $session->created_at = $item->created_at;
                            $session->updated_at = $item->updated_at;
                            $seasons->push($session);
                        }
                        $ctlimit++;
                    }
                }

                return $seasons->sortBy('rand');
            } else
                return null;
        });
        return $shows;
    }

    public static function getAllepisodesBySeason($limit = 50, $sesson)
    {
        $episodes = collect([]);

        //$this->clearCache('content_service_get_all_sessons_genre_limit_' . $genre . $limit);
        //$episodes = \Cache::remember('getAllepisodesBySeason_' . $sesson . $limit, 1440, function () use ($episodes, $limit, $sesson)
        //{
        $gepisodes = DB::table("v3_videos")
            ->select('v3_videos.id_sambavideos',
                'v3_seasons.id AS id_seasson',
                'v3_seasons.title AS seasson',
                'v3_videos.id',
                'v3_videos.title',
                'v3_videos.description',
                'v3_videos.legenda',
                'v3_videos.highlight',
                'v3_videos.duracao',
                'v3_videos.order')
            ->join('v3_videos_seasons','v3_videos.id','v3_videos_seasons.video_id')
            ->join('v3_seasons','v3_videos_seasons.season_id','v3_seasons.id')
            ->where('title_id', $sesson)
            ->orderByRaw('`v3_seasons`.`order`,position ASC')
            ->get();

        $ctlimit = 0;
        if ($gepisodes->count() > 0)
        {
            foreach ($gepisodes as $episode)
            {
                if ($ctlimit < $limit)
                {
                    $episod = new Video;
                    $episod->id_title = $episode->id;
                    $episod->id_seasson = $episode->id_seasson;
                    $episod->id_sambavideos = $episode->id_sambavideos;
                    $episod->id_project = 4307;
                    $episod->id_georegra = 6;
                    $episod->title = $episode->seasson . ' - ' . $episode->title;
                    $episod->description = $episode->description;
                    $episod->legenda = '';
                    $episod->cover = '';
                    $episod->highlight = $episode->highlight;
                    $episod->highlight2 = '';
                    $episod->background = '';
                    $episod->duracao = $episode->duracao;
                    $episod->order = $episode->order;
                    $episod->availability = 'EXTERNAL';
                    $episod->status = 1;
                    $episod->myurl = $episode->legenda;
                    $episodes->push($episod);
                }
                $ctlimit++;
            }
            return $episodes;
        } else
            return null;
        //});
        //return $episodes;
    }

    public function getBuyOrRent($limit = 24){
        $this->clearCache('content_service_get_buy_or_rent_limit_'.$limit);
        $seasons = \Cache::remember('content_service_get_buy_or_rent_limit_'.$limit, 1440, function() use ($limit) {

            $items = DB::table('pricing_item')
                ->select('item_id')
                ->where('item_type', 'season')
                ->groupBy('item_id')
                ->get()->shuffle()->pluck('item_id')->toArray();;


            $seasons = Season::whereIn('id', $items);
            if($limit > 0){
                $seasons->limit($limit);
            }
            return $seasons->with('pricing_items')->get();
        });

        return $seasons;
    }

    /**
     * @param Carbon|null $timeLimit
     * @param int $limit
     * @param bool $fillWithTitle Define se o método deve retornar a série de um determinado vídeo ou não
     * @return mixed
     *
     * Retorna uma collection com o conteúdo mais assistido de todos os tempos ou à partir da data limite $timeLimit,
     * limitado por $limit ou pelo padrão 24
     *
     */
    public function getSortSeasons(Carbon $timeLimit = null, $limit = 24, $fillWithTitle = true)
    {
        //Condicional para marcar no cache se a consulta é limitada por tempo ou não pois o parâmetro time limit muda a cada requisição
        $cacheTimeLimit = !is_null($timeLimit) ? '_time_limited_' : '_';
        $cacheFillTitle = !is_null($fillWithTitle) ? '_with_title_' : '_without_title_';

        $this->clearCache('sort_seasons'.$cacheTimeLimit.'limit_'.$limit.'_'.$cacheFillTitle);
        return \Cache::remember('sort_seasons'.$cacheTimeLimit.'limit_'.$limit.'_'.$cacheFillTitle, 30, function() use ($timeLimit, $limit, $fillWithTitle){
            $seasons = Season::where('status', 1)->where('availability', '=','SVOD')->inRandomOrder()->limit($limit)->get();
            return $seasons;

        });
    }


    /**
     * Gera lista de títulos em lançamento, tirando os vistos pelo usuário.
     * (Por enquanto retornando lista fixa de itens, precisa implementar o algoritmo disso)
     * @param array $types Tipo de title a ser retornado ('movie', 'show')
     * @param int limit Quantidade máxima a retornar
     */
    public function getLancamentos($perfil, $limit=24)
    {
        //$this->clearCache('content_service_get_lancamentos_limit_'.$limit.'_'.$perfil);
        $movies = \Cache::remember('content_service_get_lancamentos_limit_'.$limit.'_'.$perfil, 1440, function() use ($limit)
        {
            $movies = Movie::select(
                "v3_titles.id",
                "v3_titles.title",
                "v3_titles.description",
                "v3_titles.type",
                "v3_titles.cover",
                "v3_titles.highlight",
                "v3_titles.status",
                "v3_titles.availability",
                "v3_titles.created_at",
                "v3_titles.updated_at",
                "pricing_item.item_id")
                ->where('v3_titles.status', 1)
                ->leftJoin('v3_titles_genres', 'v3_titles_genres.title_id', 'v3_titles.id')
                ->leftJoin('v3_genres', 'v3_genres.id', 'v3_titles_genres.title_id')
                ->leftJoin('title_information', 'title_information.item_id', 'v3_titles.id')
                ->leftJoin('pricing_item', 'v3_titles.id', 'pricing_item.item_id')
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
                })
                ->orderBy('v3_titles.created_at','DESC');
            if($limit > 0)
                $movies->limit($limit);
            $movies = $movies
                ->distinct()
                ->get();
            $movies = $movies->filter(function ($movie)
            {
                return $movie->visible();
            });

            return $movies;
        });
        return $movies;
    }


    /**
     * Gera lista de títulos sugeridos, baseado no perfil e comportamento do usuário, por ex, idade e sexo.
     * (Por enquanto retornando lista fixa de itens, precisa implementar o algoritmo disso)
     * @param array $types Tipo de title a ser retornado ('movie', 'show')
     * @param int limit Quantidade máxima a retornar
     */
    public function getSuggestions($perfil, $types=array('movie'), $limit=24)
    {
        try
        {
            $types_cache = implode('_', $types);
            $keyName = 'content_service_get_suggestions_'.$types_cache.'_limit_'.$limit.'_'.$perfil;
            $this->clearCache($keyName);
            //return \Cache::remember($keyName, 1440, function() use ($types, $limit, $perfil)
            //{
            $assistidos = Assistido::where('id_perfis', $perfil)->groupBy('id_sambavideos')->get()->take(30);
            $videos_assistidos = collect();
            $related_category = collect();
            $assistidos->each(function($item) use ($videos_assistidos, $related_category)
            {
                if($item->episodio)
                    $videos_assistidos->push($item->episodio->show());
            });
            $videos_assistidos = $videos_assistidos->filter();
            $videos_assistidos->each(function($item) use ($related_category)
            {
                if($item && $item->categories){
                    $category_id = $item->categories;
                    if($category =  $category_id->first())
                        if($item->visible()) {
                            if($item->availability == "SVOD")
                                $related_category->push($category->titles);
                            elseif($item->availability == "TVOD")
                            {
                                $movies = Movie::
                                where("v3_titles.id", $item->id)
                                    ->where('v3_titles.status', 1)
                                    ->leftJoin('v3_titles_genres', 'v3_titles_genres.title_id', 'v3_titles.id')
                                    ->leftJoin('v3_genres', 'v3_genres.id', 'v3_titles_genres.title_id')
                                    ->leftJoin('title_information', 'title_information.item_id', 'v3_titles.id')
                                    ->leftJoin('pricing_item', 'v3_titles.id', 'pricing_item.item_id')
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
                                    })
                                    ->distinct()
                                    ->get();
                                if($movies)
                                    $related_category->push($category->titles);
                            }
                        }
                }
            });
            if($related_category->count() > 0)
                return $related_category->collapse()->unique('id')->shuffle()->take($limit);
            else
                return collect([]);
            //});
        } catch(\Exception $ex){
            \Log::error("Falha ao obter sugestões",[
                'perfil'=>$perfil,
                'types'=>json_encode($types),
                'exception'=>$ex->getMessage().' '.$ex->getLine(). ' '.$ex->getFile()
            ]);
            return collect([]);
        }
    }

    /**
     * @param int $limit
     * @param bool $returnSeasons
     * @return \Illuminate\Http\JsonResponse|static
     */
    public function getRecents($limit = 24,$returnSeasons=false)
    {
        try {
            $this->clearCache('content_service_get_recents_limit_'.$limit);
            $last_episodes = \Cache::remember('content_service_get_recents_limit_'.$limit, 1440, function() use($limit) {
                $videos = Video::select('id','id_georegra')
                    ->whereNotNull('created_at')
                    ->where('status',1)
                    ->where('created_at','>',Carbon::now()->addDays(-90))
                    ->orderBy('created_at', 'desc')
                    ->orderBy('updated_at','desc');

                if($limit > 0){
                    $videos->limit($limit);
                }
                return $videos->get();
            });



            if($returnSeasons){
                $last_episodes->transform(function ($item, $key)  {
                    if($season = $item->season()->first()){
                        return $season;
                    }
                });

                // Filtra somente seasons not null.
                $last_episodes = $last_episodes->filter();
//                $last_episodes[0] = null;
                return $last_episodes->unique('id');
            } else{
                $series = collect([]);

                foreach($last_episodes as $episode){
                    $show = $episode->show();

                    if($show && !$series->contains('id',($show->id))){
                        $title = $episode->show();
                        if($title){
                            $title->favorite = $title->isFavorited();
                            $series->push($title);
                        }
                    }
                }

                return $series->filter()->values();
            }


        } catch(\Exception $e){
            return response()->json($e->getMessage());
        }

    }

    /**
     * Gera lista de títulos relacionados a um item específico, por ex. por mesmos generos, categorias, etc.
     * @param array $types Tipo de title a ser retornado ('movie', 'show')
     * @param int limit Quantidade máxima a retornar
     */
    public function getRelated( $item, $types=array('movie','show'), $limit=30 )
    {
        $cache_types = implode('_', $types);
        $this->clearCache('content_service_get_related_'.$item.'_types_'.$cache_types.'_limit_'.$limit);
        $related_content = \Cache::remember('content_service_get_related_'.$item.'_types_'.$cache_types.'_limit_'.$limit, 1440, function() use ($item, $types, $limit) {
            $related = collect();
            if($item->getMediaType() == 'movie' || $item->getMediaType() == 'show' ){
                foreach($item->categories()->get() as $category){
                    $related->push( Title::ofCategory($category->id)->get() );
                }
                foreach($item->genres()->get() as $genre){
                    $related->push( Title::ofGenre($genre->id)->get() );
                }
            }
            if($item->getMediaType() == 'video'){
                foreach($item->tags()->get() as $tag){
                    $videos = $tag->videos()->get();
                    foreach($videos as $video){
                        if( $video->title()->count() > 0){
                            $related->push($video->title()->get());
                        }else{
                            $related->push($video->season()->get());
                        }
                    }
                }
            }
            return $related->collapse()->unique()->shuffle()->take($limit);
        });
        return $related_content;
    }

    /**
     * Busca de conteúdo
     * @param string $term Expressão a ser buscada
     * @param array $types Tipo de title a ser retornado ('movie', 'show')
     * @param int limit Quantidade máxima a retornar
     * @return mixed Retorna um array com cada coleção em um índice (ex. results['shows'] ), ou false se nenhum resultado
     */
    public function searchContent( $term, $types=array('movie','show','season','video'), $limit=24, $isApiCall = false)
    {
        $results = ['movies' => [], 'shows' => [], 'seasons' => [], 'videos'=> []];

        if(in_array('movie', $types)){
            $movies = Movie::select(
                "v3_titles.id",
                "v3_titles.title",
                "v3_titles.description",
                "v3_titles.type",
                "v3_titles.cover",
                "v3_titles.highlight",
                "v3_titles.status",
                "v3_titles.availability",
                "v3_titles.created_at",
                "v3_titles.updated_at",
                "pricing_item.item_id")
                ->where('v3_titles.status', 1)
                ->where('v3_titles.title', 'like', '%'.$term.'%')
                ->orWhere('v3_titles.description', 'like', '%'.$term.'%')
                ->leftJoin('v3_titles_genres', 'v3_titles_genres.title_id', 'v3_titles.id')
                ->leftJoin('v3_genres', 'v3_genres.id', 'v3_titles_genres.title_id')
                ->orWhere('v3_genres.title', 'like', '%'.$term.'%')
                ->leftJoin('title_information', 'title_information.item_id', 'v3_titles.id')
                ->orWhere('title_information.ano', 'like', '%'.$term.'%')
                ->orWhere('title_information.titulo_original', 'like', '%'.$term.'%')
                ->orWhere('title_information.diretor', 'like', '%'.$term.'%')
                ->orWhere('title_information.atores', 'like', '%'.$term.'%')
                ->leftJoin('pricing_item', 'v3_titles.id', 'pricing_item.item_id')
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
                })
                ->distinct()
                ->get();

            $movies = $movies->filter(function ($movie)
            {
                return $movie->visible();
            });
            $results['movies'] = $movies;
        }

        $results['videos'] = null;
        $episodes = collect([]);
        if(in_array('show', $types)) {
            $gepisodes = DB::table("v3_videos")
                ->select('v3_videos.id_sambavideos',
                    'v3_seasons.id AS id_seasson',
                    'v3_seasons.title AS seasson',
                    'v3_videos.id',
                    'v3_videos.title',
                    'v3_videos.description',
                    'v3_videos.legenda',
                    'v3_videos.highlight',
                    'v3_videos.duracao',
                    'v3_videos.availability',
                    'v3_videos.order')
                ->join('v3_videos_seasons','v3_videos.id','v3_videos_seasons.video_id')
                ->join('v3_seasons','v3_videos_seasons.season_id','v3_seasons.id')
                ->where('v3_videos.availability', 'EXTERNAL')
                ->where('v3_videos.status', 1)
                ->where('v3_seasons.status', 1)
                ->where('v3_videos.title', 'like', '%'.$term.'%')
                ->orWhere('v3_videos.description', 'like', '%'.$term.'%')
                ->orderByRaw('v3_seasons.order, position ASC')
                ->get();

            if ($gepisodes->count() > 0) {
                foreach ($gepisodes as $episode) {
                    $episod = new Video;
                    $episod->id = $episode->id;
                    $episod->id_title = $episode->id;
                    $episod->id_seasson = $episode->id_seasson;
                    $episod->id_sambavideos = $episode->id_sambavideos;
                    $episod->id_project = 4307;
                    $episod->id_georegra = 6;
                    $episod->title = $episode->seasson . ' - ' . $episode->title;
                    $episod->description = $episode->description;
                    $episod->legenda = '';
                    $episod->cover = '';
                    $episod->highlight = $episode->highlight;
                    $episod->highlight2 = '';
                    $episod->background = '';
                    $episod->duracao = $episode->duracao;
                    $episod->order = $episode->order;
                    $episod->availability = $episode->availability;
                    $episod->status = 1;
                    $episod->myurl = $episode->legenda;
                    $episodes->push($episod);
                }
                $results['shows'] = $episodes;
            }
        }

        if(in_array('season', $types)){
            $seasons = Season::where('status', 1)->where('title', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')->get();
            $seasons = $seasons->transform(function($season) {
                $show = $season->show()->first();
                if($show) {
                    return $season;
                }
                return null;
            });
            $results['seasons'] = $seasons->filter();
        }
        if( !count($results['movies']) && !count($results['shows']) && !count($results['seasons'])  && !count($results['videos'])){
            return false;
        }
        return $results;
    }

    /**
     * Gera a url de um item (movie, show, season, movie) contendo slug.
     * Para obter o id do slug usar a funão idFromSlug logo abaixo.
     * @param mixed $item Instância de um item
     */
    public function url( $item )
    {
        if(!method_exists($item,'getMediaType'))
            return '/';
        if($item->availability == "EXTERNAL")
            return Video::find($item->id)->legenda;
        $type = $item->getMediaType();
        /* Gera urls do tipo:
            /movie/20-nome-do-filme
            /show/90-nome-da-serie
            /video/99-nome-do-video
        */
        if($type=='movie' || $type=='show' || $type=='video')
        {
            if($item->getMediaType() == "video")
                $title = DB::table('v3_videos_titles')->where('video_id', ($item->title_id == null ? $item->id : $item->title_id))->first();
            else
                $title = DB::table('v3_videos_titles')->where('title_id', ($item->title_id == null ? $item->id : $item->title_id))->first();
            $season = DB::table('v3_videos_seasons')->where('video_id', $item->id)->first();
            if($season)
                return '/video/' . $season->video_id . '-' . str_slug($item->title, '-');
            elseif($title)
                return '/movie/'.$title->title_id.'-'.str_slug($item->title, '-');
            else
                return '/' . $type . '/' . ($item->title_id == null ? $item->id : $item->title_id) . '-' . str_slug($item->title, '-');
        }
        if($type=='season')
        {
            if($item->show()->first())
            {
                $show = $item->show()->first();
                return '/show/'.$item->title_id.'-'.str_slug($show->title, '-').'/'.$type.'/'.$item->id.'-'.str_slug($item->title, '-');
            }
            else
                return '/';
        }
        return '/';
    }

    /**
     * Para obter o id do slug (ex. 99-meu-filme retorna 99)
     * @param string $slug
     * @return int
     **/
    public function idFromSlug( $slug ){

        if(is_numeric($slug)){
            return $slug;
        }

        $arr = explode("-", $slug);
        if(isset($arr[0]) && is_numeric($arr[0])){
            return $arr[0];
        }
        return 0;
    }


    /**
     * Retorna conteúdo html para exibição das categorias no menu.
     *
     * @return string (conteúdo html puro)
     */
    public function categoriesList()
    {
        $html = '';
        $categorias = Category::where('status', 1)->orderBy('order', 'asc')->get();
        foreach ($categorias as $categoria) {
            $html .= '<li><a href="/'.$categoria->slug.'" class="icone-'.$categoria->slug.'"><span>'.$categoria->title.'</span></a></li>';
        }
        return $html;
    }

    /**
     * Retorna listagem de categorias ordenada pela quantidade de titles que tem.
     */
    public function getCategoriesByTitleAvailability($arrAvailability = ['SVOD','MIXED','TVOD'])
    {
        if(!config('app.buyrentcache'))
            if (\Cache::has('cat_list_'.implode(',',$arrAvailability)))
                \Cache::forget('cat_list_'.implode(',',$arrAvailability));

        return \Cache::remember('cat_list_'.implode(',',$arrAvailability),1440,function()use($arrAvailability){
            $categories = Category::all();
            $categories =  $categories->transform(function($item)use($arrAvailability)
            {
                /*echo $item->titles()->ofAvailability($arrAvailability)->join('pricing_item', 'v3_titles.id', '=', 'pricing_item.item_id')
                    ->where(function ($query) {
                        $query->where(function ($query) {
                            $query->where('date_from', '<=', Carbon::now())
                                ->where('date_to', '>=', Carbon::now());
                        })->orWhere(function ($query) {
                            $query->whereNull('date_from')
                                ->whereNull('date_to');
                        });
                    })
                    ->toSql();
                die();*/

                if($arrAvailability[0] == 'TVOD') {
                    $titles = $item->titles()->ofAvailability($arrAvailability)->join('pricing_item', 'v3_titles.id', '=', 'pricing_item.item_id')
                        ->where(function ($query) {
                            $query->where(function ($query) {
                                $query->where('date_from', '<=', Carbon::now())
                                    ->where('date_to', '>=', Carbon::now());
                            })->orWhere(function ($query) {
                                $query->whereNull('date_from')
                                    ->whereNull('date_to');
                            });
                        })
                        ->get();
                }
                else {
                    $titles = $item->titles()->ofAvailability($arrAvailability)->get();
                }
                if($titles){
                    $item->titles = $titles;
                    return $item;
                } else{
                    return null;
                }
            })->filter();

            //dd($categories);

            return $categories->sortByDesc(function ($item) {
                return count($item->titles);
            });
        });

    }

    /**
     * Retorna html do breadcrumb de conteúdo.
     *
     * @param mixed Title, Movie, Show, Season, Video
     * @return string (conteúdo html puro)
     */
    public function breadcrumb( $item )
    {
        $html = '';

        $mediaType = $item->getMediaType();

        if($mediaType == 'movie'){
            $html  = '<a href="/">Início</a>';

            $html .= ' > <a href="/filmes">Filmes</a>';
            $html .= ' > '.$item->title;
        }

        if($mediaType == 'show')
        {
            $html  = '<a href="/">Início</a>';
            try{
                $html .= ' > <a href="/'.$item->categories()->first()->slug.'">'.$item->categories()->first()->title.'</a>';
            } catch ( \Exception $e ) {
            }
            $html .= ' > '.$item->title;
        }

        if($mediaType == 'season'){
            $html  = '<a href="/">Início</a>';
            $html .= ' > <a href="/series">Séries</a>';
            $html .= ' > <a href="'.self::url($item->show()->first()).'">'.$item->show()->first()->title.'</a>';
            $html .= ' > '.$item->title;
        }

        if($mediaType == 'video'){

            if( $item->season()->count() > 0 ){
                //video pertence a uma temporada de show
                $html  = '<a href="/">Início</a>';
                $html .= ' > <a href="/series">Séries</a>';
                $html .= ' > <a href="'.self::url($item->season()->first()->show()->first()).'">'.$item->season()->first()->show()->first()->title.'</a>';
                $html .= ' > <a href="'.self::url($item->season()->first()).'">'.$item->season()->first()->title.'</a>';

            }elseif( $item->title()->first() ){
                $category = $item->title()->first()->categories() ? $item->title()->first()->categories()->first() : null;

                if(!$category){
                    $category = new \stdClass();
                    $category->slug = 'filmes';
                    $category->title = 'Filmes';
                }

                //video pertence a um movie
                $html  = '<a href="/">Início</a>';
                $html .= ' > <a href="/'.$category->slug.'">'.$category->title.'</a>';
                $html .= ' > '.$item->title;

            }else{
                //something else
                $html  = '<a href="/">Início</a>';
                $html .= ' > '.$item->title.'</a>';
            }

        }
        return $html;
    }

    /**
     * Workaround para instanciar player com parametros customizados
     * @return Video
     */
    private function getMediaFromURL(Video $video)
    {
        if(Auth::check()){
            $email = Auth::user()->email;
            // Somente em ambiente staging e um usuário autorizado podem definir um video / projeto específico para carregar no player
            if(app()->environment() === 'staging'){
                if(strpos($email,'@sambatech.com.br') || Auth::user()->tipo === 'ADMIN'){
                    if(isset($_GET['idvideo']) && strlen($_GET['idvideo']) > 0){
                        $idSambavideo = $_GET['idvideo'];
                        $video->id_sambavideos = $idSambavideo;
                    }

                    if(isset($_GET['idproject']) && strlen($_GET['idproject']) > 0){
                        $video->id_project = $_GET['idproject'];
                    }

                    // Informa que é necessário realizar o passo de autorização
                    if(isset($_GET['authorize'])){
                        $video->authorize = true;
                    }

                    // Define que o vídeo já foi autorizado (para carregar o player de forma forçada)
                    if(isset($_GET['authorized'])){
                        $video->authorized = true;
                    }
                }
            }

        }
        return $video;
    }


    /**
     * @param iMediaInterface $media
     * @returns Video
     * @throws \Exception
     */
    public function getMedia(iMediaInterface $media)
    {

        if ($media instanceof \Univer\Entities\Movie) {
            $newEpisode = $media->video();
            if (!$newEpisode) {
                throw new \Exception("Filme $media->id não tem video associado");
            } else {
                $media = $newEpisode;
            }
        }

        return $this->getMediaFromURL($media);
    }

    /**
     * Define se é
     * @param iMediaInterface $media
     * @param $user
     * @param $ex
     */
    protected function reportDrmError(iMediaInterface $media, $user, $ex)
    {
        $shouldReport = DB::table('CFG_SYS')->select('status')->where('data', 'report_internal_drm_fail_to_authorize')->where('status', 'on')->first();

        if ($shouldReport) {
            \DRMService::reportError([
                'user_id' => $user->id,
                'drm_auth' => json_encode(\DRMService::getUserSession()),
                'id_sambavideos' => $media->id_sambavideos,
                'error_code' => 0,
                'error_message' => $ex->getMessage(),

            ]);

        }
    }

    /**
     * Retorna collection com vídeos da categoria.
     * @param $id
     * @return Collection
     */
    public function getVideosFromCategory($id)
    {
        $shows = $this->getShowsOfCategory($id);

        $videos = collect([]);

        $shows->each(function($show,$key) use($videos){
            $show->seasons()->each(function($season,$key)use($videos){
                $season->videos()->each(function($video,$key)use($videos){
                    $videos->push($video);
                });
            });
        });

        return $videos;

    }

    /**
     * Retorna lista de gêneros de filmes cadastrados
     * @return mixed
     */
    public function getGenres()
    {
        //Guarda generos por um mês
        return Cache::remember('genres',17280,function(){
            return \Univer\Entities\Genre::orderBy('title')->get();
        });
    }

    public function getGenresGroup()
    {
        return Cache::remember('components.sub-menu-header-logada', 720, function()
        {
            $generos = Genre::where("status", 1)->orderBy('title')->get();
            $group = [];
            $group[0] = $group[1] = $group[2] = $group[3] = $group[4] = [];
            $ct = 0;
            foreach ($generos as $genero) {
                $titles = DB::table('v3_titles_genres')
                    ->where('genre_id', $genero->id)
                    ->join('v3_titles', 'v3_titles.id', 'v3_titles_genres.title_id')
                    ->where("status", 1)
                    ->where("availability", "SVOD")
                    ->get();
                if(count($titles) > 0)
                {
                    $genero->slug = str_slug($genero->title);
                    $group[$ct][] = $genero;
                    $ct++;
                    if ($ct > 4)
                        $ct = 0;
                }
            }
            return $group;
        });
    }

    public function getGenresContent($availability = ["SVOD", "TVOD"])
    {
        return Cache::remember('components.generos.com.titles_availability_'.join("_", $availability), 720, function() use ($availability)
        {
            $filtergenere = [];
            $generos = Genre::all()->sortBy('title');
            foreach ($generos as $genero) {
                $titles = DB::table('v3_titles_genres')
                    ->where('genre_id', $genero->id)
                    ->join('v3_titles', 'v3_titles.id', 'v3_titles_genres.title_id')
                    ->where("status", 1)
                    ->whereIn("availability", $availability)
                    ->orderBy('title')
                    ->get();
                if(count($titles) > 0)
                {
                    $filtergenere[] = (Object)[
                        'id' => $genero->id,
                        'titulo' => $genero->title,
                        'slug' => str_slug($genero->title),
                        'status' => $genero->status
                    ];
                }
            }
            return $filtergenere;
        });
    }

    public function getTitlesByGenre(Genre $genre)
    {
        return $genre->titles();
    }

    /**
     * Retorna uma view já renderizada com o submenu.
     * @return View::render()
     */
    public function getSubmenuHome($type='assinantes')
    {
        return Cache::remember('components.sub-menu_'.$type, 10,function() use($type){
            $view = $type === 'assinantes' ? 'components.sub-menu' : 'components.sub-menu-avulso';
            return \View::make($view)->render();
        });
    }
}
