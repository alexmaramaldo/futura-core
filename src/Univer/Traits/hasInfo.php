<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 11/10/16
 * Time: 18:05
 */

namespace Univer\Traits;

use Univer\Entities\TitleInformation;

trait hasInfo
{
   public function info(){
       return $this->hasOne(TitleInformation::class,'item_id',$this->getPrimaryKey())->where('item_type',$this->getMediaType());
   }

}