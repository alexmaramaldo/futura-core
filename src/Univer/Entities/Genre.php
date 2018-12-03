<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    protected $table = 'v3_genres';
    protected $primaryKey = 'id';
    public    $timestamps = false;
    protected $fillable = [
        'title',
        'status'
    ];

    public $rules = [
        'title' => 'required|max:200'
    ];

    public $messages = [];

    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    public function put($id, $data)
    {
        $object                    = Genre::find($id);
        $object->title             = $data['title'];
        $object->status            = $data['status'];
        return $object;
    }

    public function store($data)
    {
        $object                    = new Genre();
        $object->title             = $data['title'];
        $object->status            = $data['status'];
        return $object;
    }

    public function titles()
    {
        return $this->belongsToMany('Univer\Entities\Title', 'v3_titles_genres', 'genre_id', 'title_id');
    }
}
