<?php

namespace Modules\Tag\Entities;

use Illuminate\Database\Eloquent\Model;

use Spatie\MediaLibrary\Models\Media;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class Tag extends Model implements HasMedia
{
    use HasMediaTrait;

    protected $guarded = ['id'];

    /**
     * Get the tag category record associated with the tag.
     *
     * @return void
     */
    public function tag()
    {
        return $this->hasOne('Modules\Tag\Entities\TagCategory');
    }
}
