<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::view('/ranking/boy', 'rankings.boy')->name('ranking.boy');
