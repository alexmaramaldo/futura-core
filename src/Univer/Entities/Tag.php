<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'v3_tags';
    protected $primaryKey = 'id';
    public    $timestamps = false;
    protected $fillable = [
        'title'
    ];

    public $rules = [
        'title' => 'required|max:40'
    ];

    public $messages = [];

    public function validate($data, $edit = false)
    {
        return \Validator::make($data, $this->rules, $this->messages);
    }

    public function put($id, $data)
    {
        $object                    = Tag::find($id);
        $object->title             = $data['title'];
        return $object;
    }

    public function store($data)
    {
        $object                    = new Tag();
        $object->title             = $data['title'];
        return $object;
    }

    public function videos()
    {
        return $this->belongsToMany('Univer\Entities\Video', 'v3_tags_videos', 'tag_id', 'video_id');
    }

}
