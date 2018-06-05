<?php

namespace App\Http\Controllers\Cole;
use App\Http\Controllers\Controller;
use Image;
use Illuminate\Support\Facades\Cache;

class ColeController extends Controller
{
	/*
		* ColeController
		*
		* The core of the Cole CMS subsystem
		*
		* @author     Peter Day <peterday.main@gmail.com>
		* @copyright  2018-2019 Cole CMS.
	*/

	public function PageConstructor($Url,$EditMode = null){

		$Output = (object)array(
			'Page' => (object)array()
		);

		$PageMeta = \DB::table('ColeMod_Pages')
		->where('Url',$Url)
		->get();

		$PageFields = \DB::table('ColeMod_Pages_Fields')
		->where('Url',$Url)
		->orwhere('Url','{ColeBranding}')
		->get();

		$PageImages = \DB::table('ColeMod_Pages_Images')
		->get();


		$SettingsDB = \DB::table('Settings')
		->get();
		$SettingsOutput = array();
		foreach($SettingsDB as $Setting){
			$SettingsOutput[$Setting->settingCodeName] = $Setting->settingValue;
		}
		$Output->Settings = (object)$SettingsOutput;

		$ActiveAccount = app('App\Http\Controllers\Cole\ColeController')->ActiveAccount();


		// Load page meta data
		if(count($PageMeta)!=0){
			$Output->Page->PageMeta = $PageMeta[0];
		}else{
			$Output->Page->PageMeta = (object)array(
				'Url' => $Url
			); // Show the url even if no page data exists
		}

		// Load page fields
		if($PageFields){
			$Fields = array();
			foreach($PageFields as $Field){
				$Fields[$Field->Tag] = base64_decode($Field->Value);
			}
			$Output->Page->PageFields = (object)$Fields;
		}

		if(count($PageImages)!=0){
			$Images = array();
			foreach($PageImages as $Image){
				$Images[$Image->Tag] = (object)array(
					'ImageUrl' => $Image->Value,
					'Alt' => $Image->Alt
				);
			}
			$Output->Page->PageImages = (object)$Images;
		}



		// Load account data
		if($Url!="404"){
			if(count($ActiveAccount)!=0){
			$Output->Accounts = (object)$ActiveAccount;
		}else{
			// No active account. Check for accountlock
			if(isset($Output->Page->PageMeta->AccountLocked)){
				if($Output->Page->PageMeta->AccountLocked==1){
					// Page is accountlocked and no activeaccount
					// redirect to login
					header('Location: /login');
					die();
				}
			}else{
				// Page is accountlocked and no activeaccount
				// redirect to login
				header('Location: /login');
				die();
			}
		}
		}
		$Output->EditMode = $EditMode;
		
		if(isset($_GET['ColeEdit'])){
			$Output->EditToken = $_GET['ColeEdit'];
		}
		if(isset($PageMeta[0]->Plugin)){
			$Plugin = $PageMeta[0]->Plugin;
			if(method_exists('App\Http\Controllers\App\PluginController', $Plugin)){
				$Output->Plugin = app('App\Http\Controllers\App\PluginController')->$Plugin();
			}
		}

		// BUILD MODULES
		if(isset($PageMeta[0]->Modules)){
			$Modules = explode(',',$PageMeta[0]->Modules);
			$OutputModules = array();
			foreach($Modules as $Module){
				$Module = explode(':', $Module);
				$ModuleName = $Module[0];
				if(count($Module)==2){
					$ModuleFilter = $Module[1];
				}else{
					$ModuleFilter = '';
				}
				$ModuleData = \DB::table('Modules')
				->where('Codename',$ModuleName)
				->select('id','codename','Database')
				->get();
				if(count($ModuleData)!=0){
					if(!empty($ModuleFilter)){
						$ModuleFilter = explode('-', $ModuleFilter);
						$ModuleData->Content = \DB::table($ModuleData[0]->Database)
						->select($ModuleFilter)
						->get();
					}else{
						$ModuleData->Content = \DB::table($ModuleData[0]->Database)
						->get();
					}
					unset($ModuleData[0]->Database); // Dispose of database as it's no longer needed for security
					$OutputModules[$ModuleName] = $ModuleData;
				}

			}
			$Output->Modules = (object)$OutputModules;
		}
		// END BUILD MODULES
		if(isset($Output->Page->PageMeta->Panels)){
			$Output->Page->PageMeta->Panels = json_decode($Output->Page->PageMeta->Panels);
			
			foreach($Output->Page->PageMeta->Panels as $Key => $Panel){
				$Output->Page->PageMeta->Panels[$Key] = \DB::table('ColeMod_Pages_Panels')
				->where('id',$Panel)
				->first();
				$Output->Page->PageMeta->Panels[$Key]->Uid = \DB::table('ColeMod_Pages_Panels_Uids')
				->where('PageID',$PageMeta[0]->id)
				->where('PanelID',$Panel)
				->where('PanelArrayPos',$Key)
				->first()->Uid;
				
			}


		}

		return (object)$Output;
	}

	public function ActiveAccount(){
		if(isset($_COOKIE['ColeCustomer'])){
			$ColeUser = $_COOKIE['ColeCustomer'];
			$ColeUser = base64_decode($ColeUser);
			$ColeUser = json_decode($ColeUser,TRUE);
			return $ColeUser;
		}else{
			return array();
		}
	}

	public function AccountLogin(){
	    $_POST['Password'] = md5($_POST['Password'].'ColePasswordSalt');

	    $Debug = false;

	    if(!$Debug){
			$AuthenticationObject = \DB::table('ColeMod_Customers')
			->where('Email',$_POST['Email'])
			->where('Password',$_POST['Password'])
			->get();
		}else{
			$AuthenticationObject = \DB::table('ColeMod_Customers')
			->where('Email',$_POST['Email'])
			->get();
		}

		if(count($AuthenticationObject)!=0){
			$AuthenticationObject = $AuthenticationObject[0];
		    $AuthenticationObject->Password = ''; // Nullify password for security
			$expire=time()+(86400); // 1 day
			$path = "/";
			$AuthenticationObject = base64_encode(json_encode($AuthenticationObject));
			setcookie("ColeCustomer", $AuthenticationObject, $expire, $path);

			$Response = array(
				'Outcome' => 'Success'
			);

		}else{
			// Password not correct
			$Response = array(
				'Outcome' => 'Failure',
				'Reason' => 'Sorry, your login details do not appear to be correct. Please check and try again',

			);

		}

		return $Response;

    }

    public function AccountLogout(){
		$path = "/";
		$past = time() - 3600;
		setcookie("ColeCustomer", "", $past, '/');
		$Response = array('Outcome' => 'Success');
		return $Response;
    }


	/*
		Image System
	*/

	public function ColeImage($Tag,$Width = null,$Height = null){
		$Tag = urldecode($Tag);

		$ImageData = \DB::table('ColeMod_Pages_Images')
		->where('Tag',$Tag)
		->get();

		if(count($ImageData)!=0){
			$img = Image::make('Cole/Images/' . $ImageData[0]->Value);

			if(!is_null($Width) && !is_null($Height)){
				$img = $img->resize($Width, $Height, function ($constraint) {
			    	$constraint->aspectRatio();
				});
			}else if(!is_null($ImageData[0]->Width) || !is_null($ImageData[0]->Height)){
			    $img = $img->resize($ImageData[0]->Width, $ImageData[0]->Height, function ($constraint) {
			    	$constraint->aspectRatio();
				});
			}

		    if(count($_GET)==0){
			    // Load tweaks if applicable from Db
			    if(isset($ImageData[0]->Tweaks)){
				    $Tweaks = (object)json_decode($ImageData[0]->Tweaks);
				    $_GET['blur'] = $Tweaks->blur;
				    $_GET['brightness'] = $Tweaks->brightness;
				    $_GET['contrast'] = $Tweaks->contrast;
				    $_GET['flip'] = $Tweaks->flip;
				    $_GET['rotate'] = $Tweaks->rotate;
				    $_GET['greyscale'] = $Tweaks->greyscale;

			    }
		    }

			if(isset($_GET['blur'])){
				$img = $img->blur($_GET['blur']);
			}
			if(isset($_GET['brightness'])){
				$img = $img->brightness($_GET['brightness']);
			}
			if(isset($_GET['contrast'])){
				$img = $img->contrast($_GET['contrast']);
			}
			if(isset($_GET['flip'])){
				if(!empty($_GET['flip'])){
					if($_GET['flip']!="null"){
						$img = $img->flip($_GET['flip']);
					}
				}
			}
			if(isset($_GET['rotate'])){
				if(!empty($_GET['rotate'])){
					if($_GET['rotate']!="null"){
						$img = $img->rotate($_GET['rotate']);
					}
				}
			}
			if(isset($_GET['greyscale'])){
				if($_GET['greyscale']==true){
					if($_GET['greyscale']!="null"){
						$img = $img->greyscale();
					}
				}
			}

			return $img->response('jpg');

		}else{
			$Api = array(
				'Outcome' => 'Failure',
				'Reason' => 'No image Data found'
			);
			return $Api;
		}

	}

	public function ColeImageBrowse($Path = null){

		$Dir = scandir('Cole/Images/'.$Path);
		$Dir = array_diff($Dir, array('.','..','.DS_Store'));
		$Dir = array_values($Dir);

		$Output = array();

		foreach($Dir as $FileItem){

			if(is_dir('Cole/Images/'.$Path.'/'.$FileItem)){
				$type = 'default';
			}else{
				$type = 'file';
			}
			$Output[] = array(
				'name' => $FileItem,
				'isDir' => is_dir('Cole/Images/'.$Path.'/'.$FileItem),
				'text' => $FileItem,
				'children' => is_dir('Cole/Images/'.$Path.'/'.$FileItem),
				'type' => $type
			);

		}

		return $Output;

	}

	public function ColeImageSave(){

			\DB::table('ColeMod_Pages_Images')
			->where('Tag',$_POST['Tag'])
			->update([
				'Value' => $_POST['Value']
			]);

			$Api = array(
				'Outcome' => 'Success'
			);

			return $Api;

	}

	public function ColeImageFolderMake(){
		if($_POST['Path']=='/'){
			$_POST['Path'] = ''; // If root dir then fix small bug
		}
		if(!is_dir('Cole/Images/'.$_POST['Path'].'/'.$_POST['Folder'])){
			if(mkdir('Cole/Images/'.$_POST['Path'].'/'.$_POST['Folder'])){
				$Api = array(
					'Outcome' => 'Success'
				);
				chmod('Cole/Images/'.$_POST['Path'].'/'.$_POST['Folder'], 0777);

			}else{
				$Api = array(
					'Outcome' => 'Failure',
					'Reason' => 'A server error occurred whilst making this folder. Please contact support.',
					'Folder' => 'Cole/Images/'.$_POST['Path'].'/'.$_POST['Folder']
				);
			}
		}else{
			$Api = array(
				'Outcome' => 'Failure',
				'Reason' => 'This folder already exists. Please try another name.',
				'Folder' => 'Cole/Images/'.$_POST['Path'].'/'.$_POST['Folder']
			);

		}

		return $Api;
	}

	public function ColeImageUpload(){
		$data = array();

		if(isset($_GET['files']))
		{
		    $error = false;
		    $files = array();

			if(!isset($_POST['Path'])){
				$_POST['Path'] = 'Cole/Images/';
			}
		    $uploaddir = $_POST['Path'];
		    foreach($_FILES as $file)
		    {
		        if(move_uploaded_file($file['tmp_name'], $uploaddir .basename($file['name'])))
		        {
		            $files[] = $uploaddir .$file['name'];
		        }
		        else
		        {
		            $error = true;
		        }
		    }
		    $data = ($error) ? array('error' => 'There was an error uploading your files') : array('files' => $files);
		}
		else
		{
		    $data = array('success' => 'Form was submitted', 'formData' => $_POST);
		}

		return json_encode($data);
	}

	public function ColeImagePickerThumbnail(){

		if(!isset($_GET['u'])){
			return array(
				'Outcome' => 'Failure',
				'Reason' => 'No File provided'
			);
		}
		if(!file_exists('Cole/Images/'.$_GET['u'])){
			return array(
				'Outcome' => 'Failure',
				'Reason' => 'File does not exist'
			);
		}else{
			// Ready to resize
			$img = Image::make('Cole/Images/' . $_GET['u']);
			$img = $img->resize(null, 180, function ($constraint) {
		    	$constraint->aspectRatio();
			});

		    return $img->response('jpg');

		}
	}

	public function ColeImagePickerMoveFile(){

		$File = $_POST['File'];
		$File = str_replace('Cole/Images/', '', $File);
		$MoveTo = $_POST['MoveTo'];

		rename('Cole/Images/'.$File, 'Cole/Images/'.$MoveTo.'/'.$File.'');

		return array(
			'Outcome' => 'Success'
		);

	}

	/*
		Update System
	*/

    public function UpdateQuery(){

		if (Cache::has('GithubVersion')){
			$ProductionVersion = Cache::get('GithubVersion');
		} else {
			
			
			$curl = curl_init();
			curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.github.com/repos/genericmilk/Coletools/releases/latest",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'

			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);
			$ProductionVersion = json_decode($response)->tag_name;
			Cache::put('GithubVersion', $ProductionVersion, 1440);
		
		}

		if(!isset($_ENV['COLETOOLS_VERSION'])){

			// Write the production value to ENV
			$env = file_get_contents('../.env');
			file_put_contents('../.env_backup',$env); // backup env
			$env = $env.PHP_EOL."COLETOOLS_VERSION=$ProductionVersion";
			file_put_contents('../.env',$env);
			unlink('../.env_backup');			
			$CurrentVersion = $ProductionVersion;
		}else{

			$CurrentVersion = $_ENV['COLETOOLS_VERSION'];
		
		}

		if($ProductionVersion!=$CurrentVersion){
			// An update is required
			if(file_put_contents("ColeUpdatePackage.zip", fopen('https://github.com/genericmilk/coletools/archive/'.$ProductionVersion.'.zip', 'r'))){
				// File downloaded into /public
				app('App\Http\Controllers\Cole\ColeController')->FilesystemCleanup();
				$DirPreExtract = scandir('./');
				$zip = new \ZipArchive;
				$res = $zip->open('ColeUpdatePackage.zip');
				if ($res === TRUE) {
					$zip->extractTo('./'); // Extract to filesystem
					$zip->close();
					// Expanded ok
					$DirPostExtract = scandir('./');
					$Folder = array_diff($DirPostExtract, $DirPreExtract);
					$Folder = array_values($Folder);
					$Folder = $Folder[0];

					exec('mv ./'.$Folder.'/* ../');
					
					// Tidy
					exec('rm -rf '.$Folder);
					unlink('ColeUpdatePackage.zip');

					// Update version
					$env = file_get_contents('../.env');
					file_put_contents('../.env_backup',$env); // backup env
					$env = str_replace('COLETOOLS_VERSION='.$CurrentVersion, 'COLETOOLS_VERSION='.$ProductionVersion, $env);
					file_put_contents('../.env',$env);
					unlink('../.env_backup');	
					// Done!
				}else{
					// Failed to expand
				}

			}else{
				// Cannot download from URL
			}

		}


		return array(
			'Outcome' => 'Success'
		);
	}

    public function FilesystemCleanup($dir = '..') {
	    /*
		    Nuke Temporary files
		    Added by OSX and Windows
		*/

	    $exclude = array('.', '..');
        $annoying_filenames = array(
        	'.DS_Store', // mac specific
			'.localized', // mac specific
			'Thumbs.db' // windows specific
		);

        $folder = scandir($dir);
        $folder = array_diff($folder, $exclude);
        $folder = array_values($folder);

        foreach($folder as $file){
	    	$combined = ''.$dir.'/'.$file.'';

	    	try{
				if(!is_dir($combined)){
					chmod($combined, 0777);
				}
			}catch(\Exception $e) {
				// Unable to chmod
			}


	    	if(is_dir($combined)){
		    	app('App\Http\Controllers\Cole\ColeController')->FilesystemCleanup($combined);
	    	}else{
		    	if(in_array($file, $annoying_filenames)){
			    	unlink($combined);
		    	}
		    	if (strpos($combined, '._') !== false) {
			    	// Remove OSX ._ files
			    	unlink($combined);
			    }
	    	}
        }

    }

	public function ProcessUpdate(){
		$UpdateQuery = app('App\Http\Controllers\Cole\ColeController')->UpdateQuery()[0];

		if(file_put_contents("ColeUpdatePackage.zip", fopen($UpdateQuery->Url, 'r'))){

			try{
				exec('chmod -R 777 ../'); // Chmod files to allow overwriting
			}catch(\Exception $e) {
				// Unable to chmod on this server
				// This might be because of security
				// preventing exec()
			}

			// Nuke temporary files from an old install
			app('App\Http\Controllers\Cole\ColeController')->FilesystemCleanup();


			// Expand zip
			$zip = new \ZipArchive;
			$res = $zip->open('ColeUpdatePackage.zip');
			if ($res === TRUE) {
				$zip->extractTo('../'); // Extract to filesystem
				$zip->close();
				// Expanded ok

				// Test for SQL Data that needs to be processed
				if(file_exists('../ColeUpdateSQL.txt')){
					$Sql = nl2br(file_get_contents('../ColeUpdateSQL.txt')); // Load sql data and convert \n to <br />
					$Sql = trim(preg_replace('/\s+/', ' ', $Sql)); // Remove any remaining whitespace
					$Sql = explode('<br />', $Sql); // Explode at br

					foreach($Sql as $SqlCommand){
						\DB::statement($SqlCommand);
					}

					unlink('../ColeUpdateSQL.txt'); // Delete the SQL process file

				}

				// Test for any files to be deleted
				if(file_exists('../ColeDeleteFiles.txt')){
					$Files = nl2br(file_get_contents('../ColeDeleteFiles.txt')); // Load sql data and convert \n to <br />
					$Files = trim(preg_replace('/\s+/', ' ', $Files)); // Remove any remaining whitespace
					$Files = explode('<br />', $Sql); // Explode at br

					foreach($Files as $File){
						try{
							unlink('../'.$File);
						}catch(\Exception $e) {
							// Unable to delete file
						}
					}

					unlink('../ColeDeleteFiles.txt'); // Delete the SQL process file

				}


				unlink('ColeUpdatePackage.zip'); // Delete the update file

				// Clean up any new temporary files
				app('App\Http\Controllers\Cole\ColeController')->FilesystemCleanup();

				// Update system version to reflect the UpdateQuery
				\DB::table('Settings')
				->where('settingCodeName','ColeToolsVersion')
				->update([
					'settingValue' => $UpdateQuery->ToVersion
				]);

				return array(
					'Outcome' => 'Success'
				);
			} else {
				return array(
					'Outcome' => 'Failure',
					'Reason' => 'Failed to expand update into filesystem'
				);
			}
		}else{
			return array(
				'Outcome' => 'Failure',
				'Reason' => 'Failed to download update package'
			);
		}

	}

	public function ColeField($Cole,$Tag,$Element,$Classes,$ID){

		$dom = new \DOMDocument('1.0');//Create new document with specified version number
		
		if(isset($Cole->Page->PageFields->$Tag)){
			$p_text = $Cole->Page->PageFields->$Tag;
		}else{
			$p_text = 'New Cole field';
		}
		
		$p = $dom->createElement($Element, $p_text);//Create new <p> tag with text

		$domAttribute = $dom->createAttribute('data-field');//Create the new attribute 'id'
		$domAttribute->value = $Tag;//Add value to attribute
		$p->appendChild($domAttribute);//Add the attribute to the p tag

		$domAttribute = $dom->createAttribute('class');//Create the new attribute 'id'
		$domAttribute->value = 'Cole '.$Classes;//Add value to attribute
		$p->appendChild($domAttribute);//Add the attribute to the p tag

		if(isset($ID)){
			$domAttribute = $dom->createAttribute('id');//Create the new attribute 'id'
			$domAttribute->value = $ID;//Add value to attribute
			$p->appendChild($domAttribute);//Add the attribute to the p tag
				
		}

		$dom->appendChild($p);//Add to document

		return $dom->saveHTML();  

	}

	public function ColeFieldImage($Tag,$Classes,$ID){

		$dom = new \DOMDocument('1.0');//Create new document with specified version number
		
		$p = $dom->createElement('img');//Create new <p> tag with text

		$domAttribute = $dom->createAttribute('data-field');//Create the new attribute 'id'
		$domAttribute->value = $Tag;//Add value to attribute
		$p->appendChild($domAttribute);//Add the attribute to the p tag

		$domAttribute = $dom->createAttribute('src');//Create the new attribute 'id'
		$domAttribute->value = '/Cole/Pages/Images/'.$Tag;//Add value to attribute
		$p->appendChild($domAttribute);//Add the attribute to the p tag


		$domAttribute = $dom->createAttribute('class');//Create the new attribute 'id'
		$domAttribute->value = 'Cole Image '.$Classes;//Add value to attribute
		$p->appendChild($domAttribute);//Add the attribute to the p tag


		if(isset($ID)){
			$domAttribute = $dom->createAttribute('id');//Create the new attribute 'id'
			$domAttribute->value = $ID;//Add value to attribute
			$p->appendChild($domAttribute);//Add the attribute to the p tag
				
		}

		$dom->appendChild($p);//Add to document

		return $dom->saveHTML();  

	}

	public function LoadPanel(){
		
		$Page = \DB::table('ColeMod_Pages')
		->where('id',$_POST['PageID'])
		->first()->Panels;
		
		$PagePanels = json_decode($Page);
		
		$PanelData = \DB::table('ColeMod_Pages_Panels')
		->where('id',$_POST['id'])
		->first();
		
		$PagePanels[] = $_POST['id'];

		$Uid = md5(rand().rand());

		\DB::table('ColeMod_Pages_Panels_Uids')
		->insert([
			'PageID' => $_POST['PageID'],
			'PanelID' => $_POST['id'],
			'PanelArrayPos' => count($PagePanels)-1,
			'Uid' => $Uid
		]);

		$AddToCole = json_decode($_POST['Cole']);
		$AddToCole->PanelUid = $Uid;
		$_POST['Cole'] = json_encode($AddToCole);

		\DB::table('ColeMod_Pages')
		->where('id', $_POST['PageID'])
		->update(['Panels' => json_encode($PagePanels)]);

		return \View::make('Cole.Panels.'.$PanelData->Blade)->with('Cole',json_decode($_POST['Cole']));
	}
}
