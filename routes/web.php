<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\EvalsController;
use App\Http\Controllers\JobRequestController;
use App\Http\Controllers\ProController;
use App\Http\Controllers\RequestPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobRequestController::class, 'create'])->name('request');
Route::post('/requests', [JobRequestController::class, 'store'])->name('requests.store');
Route::get('/requests/{jobRequest}', [JobRequestController::class, 'show'])->name('requests.show');
Route::get('/requests/{jobRequest}/photos/{number}', [RequestPhotoController::class, 'show'])->name('requests.photo')->whereNumber('number');
Route::post('/requests/{jobRequest}/photos', [RequestPhotoController::class, 'store'])->name('requests.photos.store');
Route::post('/requests/{jobRequest}/corrections', [CorrectionController::class, 'store'])->name('requests.corrections.store');
Route::post('/requests/{jobRequest}/book', [BookingController::class, 'store'])->name('requests.book');
Route::get('/requests/{jobRequest}/booked', [BookingController::class, 'show'])->name('requests.booked');
Route::get('/requests/{jobRequest}/pro', [ProController::class, 'show'])->name('requests.pro');
Route::post('/requests/{jobRequest}/pro/actions', [ProController::class, 'store'])->name('requests.pro.actions');
Route::get('/evals', [EvalsController::class, 'show'])->name('evals');
