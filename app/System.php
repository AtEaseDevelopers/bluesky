<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    public static $country_state = [
        'MY' => [
            'Selangor' => 'Selangor',
            'Kuala Lumpur' => 'Kuala Lumpur',
            'Pulau Pinang' => 'Pulau Pinang',
            'Johor' => 'Johor',
            'Kedah' => 'Kedah',
            'Kelantan' => 'Kelantan',
            'Labuan' => 'Labuan',
            'Melaka' => 'Melaka',
            'Negeri Sembilan' => 'Negeri Sembilan',
            'Pahang' => 'Pahang',
            'Perak' => 'Perak',
            'Perlis' => 'Perlis',
            'Putrajaya' => 'Putrajaya',
            'Sabah' => 'Sabah',
            'Sarawak' => 'Sarawak',
            'Terengganu' => 'Terengganu'
        ],
    ];
}