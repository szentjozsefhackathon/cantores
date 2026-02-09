<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirstName extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'gender',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => 'string',
        ];
    }

    /**
     * Get the available gender options.
     *
     * @return array<string, string>
     */
    public static function genderOptions(): array
    {
        return [
            'male' => __('Male'),
            'female' => __('Female'),
        ];
    }
}
