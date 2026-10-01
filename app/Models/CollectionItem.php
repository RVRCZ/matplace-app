<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One model of a collection: a designer's card or a model of the inspiration catalogue. */
class CollectionItem extends Model
{
    protected $fillable = ['collection_id', 'designer_model_id', 'catalog_model_id', 'position'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function designerModel(): BelongsTo
    {
        return $this->belongsTo(DesignerModel::class);
    }

    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
    }
}
