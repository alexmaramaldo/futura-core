<?php

/**
 * Retorna a instancia correta baseada no tipo da entidade e no seu ID.
 * @param null|int|iMediaInterface $idItem
 * @param $tipoItem
 * @return \Univer\Contracts\iMediaInterface
 * @throws \Exception
 */
function getMediaInstance($idItem = null, $tipoItem)
{


    //dd($tipoItem);
    // Passamos sempre um request pelo get instance. porém em alguns casos
    // o usuário enviará um objeto já instanciado
    if(!is_int($idItem)){
        return $idItem;
    }

    $obj = null;

    switch ($tipoItem){
        case 'movie':
            $obj = new \Univer\Entities\Movie();
            break;
        case 'show':
            $obj = new \Univer\Entities\Show();
            break;
        case 'season':
            $obj = new \Univer\Entities\Season();
            break;
        case 'video':
        case 'episode':
            $obj = new \Univer\Entities\Video();
            break;
    }

    //dd($idItem);

    if(!$obj){
        return null;
    }

    if(is_int($idItem)){
        $obj = $obj::where('id',$idItem)->first();
        if ($obj){
            return $obj;
        } else{
            return null;
        }
    } else{
        return $obj;
    }
}

function translateMediaTypeEngToPt($string){
    switch ($string){
        case 'show':
            return 'sua série';
            break;
        case 'video':
            return 'seu episódio';
            break;
        case 'season':
            return 'esta temporada';
            break;
        case 'movie':
            return 'seu filme';
            break;
        default:
            return null;
            break;
    }
}

function translateMediaType($string,$pt=false){

    if($pt){
        return translateMediaTypeEngToPt($string);
    }
    switch ($string){
        case 'serie':
        case 'series':
            return 'show';
            break;
        case 'episodio':
            return 'video';
            break;
        case 'temporada':
            return 'season';
            break;
        case 'filme':
            return 'movie';
            break;
        default:
            return null;
            break;
    }
}