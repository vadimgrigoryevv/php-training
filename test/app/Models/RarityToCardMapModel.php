<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for gacha items data
 */
class RarityToCardMapModel extends Model
{
    // Set the table name
    protected $table = 'rarity_to_card_map';

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
        'rarity_level',
        'card_id',
        'card_weight'
    ];
}
