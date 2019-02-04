<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
	public $timestamps = false;
	
    protected $fillable = ['img', 'img', 'url', 'title', 'producer', 'city', 'area', 'price'];

}
