<?php

use Illuminate\Support\Facades\Route;

Route::get('/chat', function () {
    return view('chat');
});
