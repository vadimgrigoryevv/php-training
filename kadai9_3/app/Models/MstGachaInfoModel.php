<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ガチャアイテムの重み付け
 */
class MstGachaInfoModel extends Model
{
    // Set the table name
    protected $table = 'mst_gacha_info';

    // The primary key associated with the table.
    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'gacha_id',
        'number_of_cards',
        'minimum_rarity_lastgacha',
    ];
}
