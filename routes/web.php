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
use Illuminate\Support\Facades\Route;


try {
	$Pages = \DB::table("ColeMod_Pages")
	->get();
}
catch (\Exception $e) {
	abort(401,'Cole was unable to load the pages database table. Please check that you have configured your .env file correctly.');
}

if(count($Pages)==0){
	abort(401,'You do not have any pages in your Pages database table. You need at least one to start your Cole website');
}

foreach($Pages as $Page){

	Route::any($Page->Url, function () {

		$Url = Route::getFacadeRoot()->current()->uri();

		if(empty($Url)){
			$Url = '/';
		}

		$PageToploader = \DB::table('ColeMod_Pages')
		->where('Url',$Url)
		->first();
		$Template = $PageToploader->Template;
		$AccountLocked = $PageToploader->AccountLocked;

		if(isset($_GET['ColeEdit'])){
			// Check for secret match
			$AffectorLookup = \DB::table('Users')
			->where('id',$_GET['Affector'])
			->where('Secret',$_GET['ColeEdit'])
			->get();

			if(count($AffectorLookup)!=0){
				$EditMode = true;
			}else{
				$EditMode = false;
			}
		}else{
			$EditMode = false;
		}
		$Cole = app('App\Http\Controllers\Cole\ColeController')->PageConstructor($Url,$EditMode);
		if(isset($_GET['ColeJSON'])){
			return response()->json($Cole);
		}else{
			return View::make($Template)->with('Cole',$Cole);
		}
	});

}

// Errors
\View::composer('errors::404', function($view)
{
	// Ensure the Cole Object comes in even in 404 instances
	$Cole = app('App\Http\Controllers\Cole\ColeController')->PageConstructor('404');
    $view->with('Cole', $Cole);
});
\View::composer('errors::503', function($view)
{
	// Ensure the Cole Object comes in even in 404 instances
	$Cole = app('App\Http\Controllers\Cole\ColeController')->PageConstructor('404');
    $view->with('Cole', $Cole);
});


// Images subsystem
Route::get('/Cole/Pages/Images/{Tag}/{Width?}/{Height?}', ['uses' =>'Cole\ColeController@ColeImage']);
Route::get('/Cole/Pages/ImgBrowse/{Path?}', ['uses' =>'Cole\ColeController@ColeImageBrowse']);
Route::post('/Cole/Pages/ImgSave', ['uses' =>'Cole\ColeController@ColeImageSave']);
Route::post('/Cole/Pages/ImgFolderMake', ['uses' =>'Cole\ColeController@ColeImageFolderMake']);
Route::post('/Cole/Pages/ImgUpload', ['uses' =>'Cole\ColeController@ColeImageUpload']);
Route::get('/Cole/Pages/ImgPickerThumbnail', ['uses' =>'Cole\ColeController@ColeImagePickerThumbnail']);
Route::post('/Cole/Pages/ImgMoveFile', ['uses' =>'Cole\ColeController@ColeImagePickerMoveFile']);

// Panels subsystem
Route::any('/Cole/Panels/Load', ['uses' =>'Cole\ColeController@LoadPanel']);

// ColeTools Update
Route::get('/Cole/Update/Query', ['uses' =>'Cole\ColeController@UpdateQuery']);
Route::get('/Cole/Update/Run', ['uses' =>'Cole\ColeController@ProcessUpdate']); // construct module

// ** CUSTOM ROUTES FOR THIS SITE **
include('cole.php');