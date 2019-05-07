<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Univer\Scopes\AvailabilityScope;

class Category extends Model
{

    protected $table = 'v3_categories';
    protected $primaryKey = 'id';
    protected $fillable = [
        'title',
        'id_georegra',
        'type',
        'slug',
        'order',
        'highlight',
        'watermark',
        'status'
    ];
    public $rules = [
        'title' => 'required|max:40',
        'slug' => 'required|max:40'
    ];
    public $messages = [];

    public function titles()
    {
        return $this->belongsToMany('Univer\Entities\Title', 'v3_titles_categories', 'category_id', 'title_id');
    }

    public function seasons()
    {
        return $this->belongsToMany('Univer\Entities\Season', 'v3_seasons_categories', 'category_id', 'season_id');
    }

    public function georegra()
    {
        return $this->belongsTo('App\V2Georegra', 'id_georegra');
    }
    public function put($id, $data)
    {
        $object                     = Category::find($id);
        $object->id_georegra        = $data['id_georegra'];
        $object->title              = $data['title'];
        $object->slug               = $data['slug'];
        $object->type               = $data['type'];
        $object->order              = $data['order'];
        $object->status             = $data['status'];
        return $object;
    }
    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    public function store($data)
    {
        $object                     = new Category();
        $object->id_georegra        = $data['id_georegra'];
        $object->title              = $data['title'];
        $object->slug               = $data['slug'];
        $object->type               = $data['type'];
        $object->order              = $data['order'];
        $object->status             = $data['status'];
        return $object;
    }

}
