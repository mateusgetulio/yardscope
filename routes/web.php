<?php

use App\Http\Controllers\JobRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobRequestController::class, 'create'])->name('request');
Route::post('/requests', [JobRequestController::class, 'store'])->name('requests.store');
Route::get('/requests/{jobRequest}', [JobRequestController::class, 'show'])->name('requests.show');
Route::get('/requests/{jobRequest}/photos/{number}', [JobRequestController::class, 'photo'])->name('requests.photo')->whereNumber('number');
