<?php

declare(strict_types=1);

use App\Http\Controllers\StatementController;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');
Route::get('/accessibilite', [StatementController::class, 'accessibility'])->name('accessibility');
Route::get('/ecoconception', [StatementController::class, 'ecodesign'])->name('ecodesign');
