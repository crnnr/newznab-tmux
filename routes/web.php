<?php

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

Route::get('/', 'BasePageController@index');

Auth::routes();

Route::get('login', 'Auth\LoginController@showLoginForm');
Route::post('login', 'Auth\LoginController@login')->name('login');

Route::get('register', 'Auth\RegisterController@showRegistrationForm');
Route::post('register', 'Auth\RegisterController@register');

Route::get('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');
Route::post('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');

Route::get('resetpassword', 'Auth\ResetPasswordController@reset');
Route::post('resetpassword', 'Auth\ResetPasswordController@reset');

Route::get('profile', 'ProfileController@show');

Route::get('browse', 'BrowseController@group');

Route::prefix('browse')->group(function () {
    Route::get('group', 'BrowseController@group');
    Route::get('all', 'BrowseController@index');
    Route::get('{parentCategory}/{id?}', 'BrowseController@show');
});

Route::get('anime/{id?}', 'AnimeController@index');

Route::get('books/{id?}', 'BooksController@index');

Route::prefix('cart')->group(function () {
    Route::get('index', 'CartController@index');
    Route::get('add/{id}', 'CartController@store');
    Route::post('add/{id}', 'CartController@store');
    Route::get('delete/{id}', 'CartController@destroy');
    Route::post('delete/{id}', 'CartController@destroy');
});

Route::get('api', 'Api\ApiController@api')->middleware('api');
Route::post('api', 'Api\ApiController@api')->middleware('api');

Route::get('details/{guid}', 'DetailsController@show');
Route::post('details/{guid}', 'DetailsController@show');

Route::get('getnzb', 'GetNzbController@getNzb');
Route::post('getnzb', 'GetNzbController@getNzb');

Route::get('rss', 'RssController@rss');

Route::post('rss', 'RssController@rss');

Route::get('profile', 'ProfileController@show');

Route::get('apihelp', 'ApiHelpController@index');

Route::get('logout', 'Auth\LoginController@logout')->name('logout');

Route::get('console', 'ConsoleController@show');

Route::get('browsegroup', 'BrowseGroupController@show');

Route::get('content', 'ContentController@show');

Route::post('content', 'ContentController@show');

Route::get('failed', 'FailedReleasesController@show');

Route::post('failed', 'FailedReleasesController@show');

Route::get('games', 'GamesController@show');

Route::get('movies', 'MovieController@showMovies');

Route::get('movie', 'MovieController@showMovie');

Route::get('movietrailers', 'MovieController@showTrailer');

Route::post('movies', 'MovieController@showMovies');

Route::post('movie', 'MovieController@showMovie');

Route::post('movietrailers', 'MovieController@showTrailer');

Route::get('/pc', function () {
    redirect('/pc');
})->middleware('auth');

Route::get('/music', function () {
    redirect('/music');
})->middleware('auth');

Route::get('/xxx', function () {
    redirect('/xxx');
})->middleware('auth');

Route::get('contact-us', 'ContactUsController@showContactForm');
Route::post('contact-us', 'ContactUsController@contact');

Route::get('forum', 'ForumController@forum');

Route::post('forum', 'ForumController@forum');

Route::get('forumpost/{id}', 'ForumController@getPosts');

Route::post('forumpost/{id}', 'ForumController@getPosts');

Route::get('/profileedit', function () {
    redirect('/profileedit');
})->middleware('auth');

Route::post('/profileedit', function () {
    redirect('/profileedit');
})->middleware('auth');

Route::get('/profile_delete', function () {
    redirect('/profile_delete');
})->middleware('auth');

Route::post('/profile_delete', function () {
    redirect('/login');
})->middleware('guest');

Route::get('/search', function () {
    redirect('/search');
})->middleware('auth');

Route::post('/search/{id}', function () {
    redirect('/search');
})->middleware('auth', 'api');

Route::get('filelist/{guid}', 'FileListController@show');

Route::post('filelist/{guid}', 'FileListController@show');

Route::get('btc_payment', 'BtcPaymentController@show');

Route::post('btc_payment', 'BtcPaymentController@show');

Route::get('btc_payment_callback', 'BtcPaymentController@callback');

Route::post('btc_payment_callback', 'BtcPaymentController@callback');
