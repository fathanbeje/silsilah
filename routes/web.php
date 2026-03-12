<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\BackupsController;
use App\Http\Controllers\BirthOrderController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\ClaimRegistrationController;
use App\Http\Controllers\CouplesController;
use App\Http\Controllers\FamilyActionsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicUserEditRequestsController;
use App\Http\Controllers\UserMarriagesController;
use App\Http\Controllers\UserEditRequestsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\GedcomController;
use App\Http\Controllers\DeploySyncController;
use App\Http\Controllers\DomainFamilyScopesController;
use App\Http\Controllers\RegistrationRequestsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::controller(UsersController::class)->group(function () {
    Route::get('/', 'search')->name('users.search');
    Route::get('profile-search', 'search')->name('users.search.page');
    Route::get('profile-search/autocomplete', 'autocomplete')->name('users.autocomplete');
    Route::get('users/{user}/chart', 'chart')->middleware('family.scope')->name('users.chart');
});

Route::post('users/{user}/claim-registration', [ClaimRegistrationController::class, 'store'])->middleware('family.scope')->name('claim-registration.store');
Route::post('users/{user}/registration-requests', [RegistrationRequestsController::class, 'store'])->middleware('family.scope')->name('registration-requests.store');
Route::get('users/{user}/edit-requests/create', [PublicUserEditRequestsController::class, 'create'])->middleware('family.scope')->name('user-edit-requests.create');
Route::post('users/{user}/edit-requests', [PublicUserEditRequestsController::class, 'store'])->middleware('family.scope')->name('user-edit-requests.store');

Route::middleware('auth')->group(function () {
    Route::controller(HomeController::class)->group(function () {
        Route::get('home', 'index')->name('home');
        Route::get('profile', 'index')->name('profile');
    });

    Route::controller(FamilyActionsController::class)->group(function () {
        Route::post('family-actions/{user}/set-father', 'setFather')->name('family-actions.set-father');
        Route::post('family-actions/{user}/set-mother', 'setMother')->name('family-actions.set-mother');
        Route::post('family-actions/{user}/add-child', 'addChild')->name('family-actions.add-child');
        Route::post('family-actions/{user}/add-wife', 'addWife')->name('family-actions.add-wife');
        Route::post('family-actions/{user}/add-husband', 'addHusband')->name('family-actions.add-husband');
        Route::post('family-actions/{user}/set-parent', 'setParent')->name('family-actions.set-parent');
    });

    Route::controller(UsersController::class)->group(function () {
        Route::get('users/{user}', 'show')->name('users.show');
        Route::get('users/{user}/edit', 'edit')->name('users.edit');
        Route::patch('users/{user}', 'update')->name('users.update');
        Route::get('users/{user}/tree', 'tree')->name('users.tree');
        Route::get('users/{user}/death', 'death')->name('users.death');
        Route::patch('users/{user}/photo-upload', 'photoUpload')->name('users.photo-upload');
        Route::delete('users/{user}', 'destroy')->name('users.destroy');
    });

    Route::get('users/{user}/marriages', [UserMarriagesController::class, 'index'])->name('users.marriages');

    Route::get('birthdays', [BirthdayController::class, 'index'])->name('birthdays.index');
    
    /**
     * Couple/Marriages Routes
     */
    Route::controller(CouplesController::class)->group(function () {
        Route::get('couples/{couple}', 'show')->name('couples.show');
        Route::get('couples/{couple}/edit', 'edit')->name('couples.edit');
        Route::patch('couples/{couple}', 'update')->name('couples.update');
    });

    Route::controller(ChangePasswordController::class)->group(function () {
        Route::get('password/change', 'show')->name('password_change');
        Route::post('password/change', 'update')->name('password_update');
    });
});

/**
 * Admin only routes
 */
Route::group(['middleware' => ['auth', 'admin']], function () {
    /**
     * Backup Restore Database Routes
     */
    Route::controller(BackupsController::class)->group(function () {
        Route::post('backups/upload', 'upload')->name('backups.upload');
        Route::post('backups/{fileName}/restore', 'restore')->name('backups.restore');
        Route::get('backups/{fileName}/dl', 'download')->name('backups.download');
    });
    Route::resource('backups', BackupsController::class);

    Route::controller(BirthOrderController::class)->group(function () {
        Route::get('birth-orders', 'index')->name('birth-orders.index');
        Route::post('birth-orders', 'update')->name('birth-orders.update');
    });

    Route::controller(DeploySyncController::class)->group(function () {
        Route::get('deploy-sync', 'index')->name('deploy-sync.index');
        Route::post('deploy-sync/run', 'run')->name('deploy-sync.run');
    });

    Route::get('gedcom/import', [GedcomController::class, 'index'])->name('gedcom.index');
    Route::post('gedcom/import', [GedcomController::class, 'store'])->name('gedcom.store');

    Route::resource('domain-family-scopes', DomainFamilyScopesController::class)->except(['show']);

    Route::controller(RegistrationRequestsController::class)->group(function () {
        Route::get('registration-requests', 'index')->name('registration-requests.index');
        Route::patch('registration-requests/{registrationRequest}', 'update')->name('registration-requests.update');
    });

    Route::controller(UserEditRequestsController::class)->group(function () {
        Route::get('user-edit-requests', 'index')->name('user-edit-requests.index');
        Route::get('user-edit-requests/{userEditRequest}', 'show')->name('user-edit-requests.show');
        Route::patch('user-edit-requests/{userEditRequest}', 'update')->name('user-edit-requests.update');
    });

    Route::patch('users/{user}/quick-deceased', [UsersController::class, 'updateQuickDeceased'])->name('users.quick-deceased');
});
