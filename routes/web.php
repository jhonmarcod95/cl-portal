<?php

use App\Http\Controllers\CreditLimitController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // dashboard
    // // Route::get('/home', 'HomeController@index')->name('home');
    Route::get('/cl_data/{from}/{to}', [DashboardController::class, 'cl_data']);

    // // so version
    // Route::get('/get-so-version/{so_number}/{date_time}', 'DashboardController@get_so_vesion');

    // remarks
    Route::get('/cl_remarks_data/{so_number}', [DashboardController::class, 'cl_remarks_data']);

    // add remarks
    Route::post('/cl_remarks', [DashboardController::class, 'cl_remarks']);

    // // cl record
    // Route::get('/cl_record', 'CreditLimitController@index');
    // Route::get('/cl-record-data/{dateFrom}/{dateTo}', 'CreditLimitController@indexData');


    // Admin role
    Route::group(['middleware' => ['role:admin']], function () {
        // approve
        Route::post('/cl_approve', [DashboardController::class, 'cl_approve']);

        // mass approve
        Route::post('/cl_mass_approve', [DashboardController::class, 'cl_mass_approve']);

        // reject
        Route::post('/cl_reject', [DashboardController::class, 'cl_reject']);

        // mass reject
        Route::post('/cl_mass_reject', [DashboardController::class, 'cl_mass_reject']);

        // remarks
        Route::get('/fetch-approve-remarks', [DashboardController::class, 'fetchApproveRemarks']);
        Route::get('/fetch-reject-remarks', [DashboardController::class, 'fetchRejectRemarks']);

    //     Route::resource('user', 'UserController', ['except' => ['show']]);
    //     Route::put('change_password/{id}', 'UserController@change_password');
    });

    // // reports
    // Route::get('/report/remarks', 'ReportsController@remarks');
    // Route::get('/report/remarks-data/{dateFrom}/{dateTo}', 'ReportsController@remarksData');
    // Route::get('/report/remarks-count/{so_number}', 'ReportsController@remarksCount');


    // Route::get('profile', ['as' => 'profile.edit', 'uses' => 'ProfileController@edit']);
    // Route::put('profile', ['as' => 'profile.update', 'uses' => 'ProfileController@update']);
    // Route::put('profile/password', ['as' => 'profile.password', 'uses' => 'ProfileController@password']);

    Route::post('/log-api-error', [DashboardController::class, 'logApiError']);
});

//// store cl
Route::get('/storeClToStorage', [CreditLimitController::class, 'storeClToStorage']);
Route::get('/test-api', [CreditLimitController::class, 'testApi']);


require __DIR__.'/settings.php';
