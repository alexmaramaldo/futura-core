<?php

namespace Univer\Transformers;

use Illuminate\Support\Collection;

abstract class AbstractTransformer
{
    /**
     * Transforma uma coleção de acordo com o método transform da classe
     * @param $collection
     * @return \Illuminate\Support\Collection
     */
    public function transformCollection($collection)
    {
        if (!$collection instanceof Collection) {
            $collection = collect([$collection]);
        }
        return $collection->map(function ($item, $key) {
            return $this->transform($item);
        });
    }

    public abstract function transform($item);

}