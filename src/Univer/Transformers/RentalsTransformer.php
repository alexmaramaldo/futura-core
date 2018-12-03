<?php
#        {
#            "rented_object":{
#                "type":"serie(serie|temporada|episodio)",
#                "id": 12345,
#                "name": "Nome da Série",
#                "cover":"",
#                "highlight":"",
#            },
#              "rental_type":"buy|rent",
#              "dates":{
#            "rented_at": "2016-10-09 12:33:50",
#                "payment_aproved_at": "2016-10-09 12:34:00",
#                "expire_from_library":"2016-11-09",
#                "start_view_date":null,
#                "expire_from_view":null
#              },
#              "status":"active"
#          },

namespace Univer\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Univer\Contracts\iMediaInterface;

class RentalsTransformer
{

    public function transformCollection($item){
        if(!$item){
            return false;
        }
        if(!$item instanceof Collection){
            $item = collect([$item]);
        }
        return $item->map(function($item,$key){
           return $this->transform($item);
        });
    }


    /**$item->rented_object = getMediaInstance($item->item_id,$item->item_type);
     * @param iMediaInterface $item
     * @return array
     * @throws \Exception
     */
    public function transform($item)
    {

        $object = getMediaInstance($item->item_id,$item->item_type);

        $dates=[];
        $dates['rented_at']             = $item->created_at->format('Y-m-d H:i:s');
        $dates['expire_from_library']   = strlen($item->expiration_date) <= 0 ? null : Carbon::createFromFormat('Y-m-d H:i:s',$item->expiration_date)->format('Y-m-d H:i:s');
        $dates['start_view_date']       = strlen($item->start_at) <= 0 ? null : Carbon::createFromFormat('Y-m-d H:i:s',$item->start_at)->format('Y-m-d H:i:s');
        $dates['expire_from_view']      = strlen($item->start_at) <= 0 ? null : Carbon::createFromFormat('Y-m-d H:i:s',$item->start_at)->addDays(2)->format('Y-m-d H:i:s');

        $type = $object->getMediaType();

        $arr =[
            "rented_object"=>[
                "type"      =>$type,
                "id"        =>$object->getMediaId(),
                "cover"     =>$object->cover,
                "title"     =>$object->title,
                "highlight" =>$object->highlight,
            ],
            "rental_type"=>$item->rental_type,
            "dates"=>$dates
        ];

        if($type === 'video' || $type === 'episode'){
            $arr['rented_object']['id_sambavideos'] = $object->id_sambavideos;
            if(method_exists($object,'season')){
                if($object->season()->first()){
                    $season = $object->season()->first();
                    $show = $season->show;
                    $arr['rented_object']['title'] = $show->title;
                    $arr['rented_object']['id_show'] = $show->id;
                    $arr['rented_object']['id_season'] = $season->id;
                    $arr['rented_object']['season'] = $season->getOrder();
                    $arr['rented_object']['episode'] = $object->getOrder();
                    $arr['rented_object']['cover'] = $season->cover;
                    $arr['rented_object']['highlight'] = $season->highlight;
                }
            }

            $arr['rented_object']['id_project'] = null;

            if(!is_null($object->id_project)){
                $arr['rented_object']['id_project'] = $object->id_project;
            }
        }

        if($type === 'season'){
            $show = $object->show;
            $arr['rented_object']['id_show'] = $show->id;
            $arr['rented_object']['title'] = $show->title;
            $arr['rented_object']['cover'] = $show->cover;
            $arr['rented_object']['highlight'] = $show->highlight;
            $arr['rented_object']['season'] = $object->getOrder();
        }

        return $arr;
    }


}