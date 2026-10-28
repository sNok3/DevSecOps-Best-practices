<?php

use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/notes');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('notes', NoteController::class);

    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->middleware('throttle:uploads')->name('files.store');
    Route::get('/files/{file}', [FileController::class, 'download'])->name('files.download');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::get('/security-log', [SecurityLogController::class, 'index'])->name('security-log');
    });
});
