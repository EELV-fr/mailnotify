<?php
/**
* ownCloud - MailNotify Plugin
* 
* OC_MailNotify_Mailing Class
*
*/
class OC_MailNotify_Mailing {

	// do not notify for following folders
	public static $no_notify_folders = array('fakeGroup1','fakeGroup2');
	public static $minimum_queue_delay = 0;

	
	//==================== PUBLIC ==============================//
/** @deprecated */
	 public static function main($path){
		\OCP\Util::writeLog('mailnotify', 'The main() function found at line '.__LINE__.' is depricated. use queue_fileChange_notification() insted', \OCP\Util::WARN);
		self::queue_fileChange_notification($path);
	}
/** @deprecated */	 
	public static function db_notify_group_members(){
		\OCP\Util::writeLog('mailnotify', 'The db_notify_group_members() function found at line '.__LINE__.' is depricated. use do_notification_queue() insted', \OCP\Util::WARN);
		self::do_notification_queue();
	}	
		

		
	/**
	 *  Add file change to the notification queue in the database 
	 * trigger on file/folder change/upload
	 * @param $path Path of the modified file. 
 	 * @return void 
	 */		
	public static function queue_fileChange_notification($path){
 		$timestamp = time();
		self::db_insert_upload(OCP\User::getUser(),  $timestamp,$path['path']);	
	}
	


	/**
	 *  Direct notification of an internal message (no queue delay)
	 * @param $fromUid uid of the message author. 
	 * @param $toUid destination uid. 
	 * @param $msg message content. 
	 * @return void 
	 */
	public static function email_IntMsg($fromUid, $toUid, $msg){		
		$l = new OC_L10N('mailnotify');
		$intMsgUrl = OCP\Util::linkToAbsolute('index.php/apps/internal_messages');
		
		$text = "You have a new message from <b>$fromUid</b>.
				<p><br>$msg<br></p>
				Please log in to <a href=\"$intMsgUrl\">%s</a> to reply.<br>";
	
		OC_MailNotify_Mailing::sendEmail($text,$l->t('New message from '.$fromUid),$toUid);
	}

		
	
	/**
	 *  check for pending notification and send corresponding emails (trigger by cronjob) 
	 * @return void 
	 */
	static public function do_notification_queue(){
		$l = new OC_L10N('mailnotify');
		$nm_upload = self::db_get_nm_upload();
		$shares = self::db_get_share();
		$mailTo = array();
		$filesList = array(); 
				
		//list all unique nm_upload path. add most recent timestamp and list editors.
		foreach ($nm_upload as $row) {		
			$filesList[$row['path']] = array();
			if ( !isset($filesList[$row['path']]['timestamp']) || $filesList[$row['path']]['timestamp'] < $row['timestamp'] ) {
				$filesList[$row['path']]['timestamp'] = $row['timestamp']; 
			}
		}
		
		
		
		
/* 		["item_source"]=>
  string(1) "9"
  ["item_target"]=>
  string(2) "/9"
  ["file_source"]=>
  string(1) "9"
  ["file_target"]=>
  string(26) "/fffffffffffffffffffffffff"
 * 
 * OCP\Share::getUsersItemShared	(	 	$itemType,
 	$itemSource,
 	$uidOwner,
 	$includeCollections = false 
)	
 * 
 * 
 * 
static OCP\Share::getItemSharedWithBySource	(	 	$itemType,
 	$itemSource,
 	$format = self::FORMAT_NONE,
 	$parameters = null,
 	$includeCollections = false 
)
 * 
 * static OCP\Share::getUsersSharingFile	(	 	$path,
 	$user,
 	$includeOwner = false 
)			
 * 
 *   $root = \OC\Files\Filesystem::getRoot();	
 * 
 * $uidOwner 
 * 
		*/
		
		
		
		
		// find who want wich notifications
		foreach ($filesList as $key => $file) {
			foreach ($shares as $row) {
				echo "<hr>";
				echo 'searching for '.$key.'<br>' ;
				
			
				 echo '<br>'.$row["share_with"];
				 echo '<br>'.$row["file_target"].'<br>';
				 ;
				 	// var_dump(OC\Files\Filesystem::initMountPoints(	 $row["share_with"]	));
				 var_dump(OC\Files\Filesystem::getPath	($row["file_target"]));
			;

				
				if ( self::db_folder_is_shared_with_me($row ) && !self::is_uid_exclude($row['uid_owner'],$key) ){					
					$mailTo[$row['share_with']][] = $key;	
				}					
			} 	
		} 

		//assamble emails
		$msg = '';
		foreach ($mailTo as $uid => $files) {
			$msg.= '<ul>';
			foreach ($files as $file) {
				$url_path = OCP\Util::linkToAbsolute('files','index.php').'/download'.OC_Util::sanitizeHTML($file);
				$url_name = basename($file);								
				$msg .='<li><a href="'.$url_path.'" target="_blank">'.$url_name.'</a></li>';	
			//	OC_MailNotify_Mailing::db_remove_all_nmuploads_for($file);//TODO not a good place to be no email send verification 
			}	
			$msg .='</ul>';
			OC_MailNotify_Mailing::sendEmail($msg,$l->t('New upload'),$uid);	
		}
	}

	
	
	
	
	
	

//================= PRIVATE ===============================//


	private static function sendEmail($msg,$action,$toUid){
 	$l = new OC_L10N('mailnotify');
					
		$txtmsg = '<html><p>Hi, '.$uid.', <br><br>';
		$txtmsg .= '<p>'.$msg;
		$txtmsg .= $l->t('<p>This e-mail is automatic, please, do not reply to it.</p></html>');
echo $txtmsg;
echo "\n====================================================\n";
 		$result = OC_Mail::send(
 			OC_MailNotify_Mailing::db_get_mail_by_user($toUid),
		 	$toUid,
		 	'['.getenv('SERVER_NAME')."] - ".$action,
		 	$txtmsg,
		 	'Mail_Notification@'.getenv('SERVER_NAME'),
		 	'',1,'','','','' 
		);		
	}
	
	
	
	// check if $path shoud be excluded form $uid notifications.
	// @return true if shoud be excluded false if not
	private static function is_uid_exclude($uid,$path){
	
		// hardcoded static exclusion array 
	 	foreach (self::$no_notify_folders as $folder) {
			if ( basename($path) == $folder ) {
				return true;
			}			 
		 }

		// database user preferances
		if ( OC_MailNotify_Mailing::db_user_setting_get_status($uid, $path) !== 'enable') {
			return true;	
		}
		
		//exclude creator of change
		$found = 0 ; 
		foreach (self::db_get_nm_upload() as $row) {
			if ($uid == $row['uid']) {
				$found++;				
			}		
		}
		if ($found == 1) {
			return true;
		}
		
		//ignore if the most recent notification is inside the time buffer 
		foreach (self::db_get_nm_upload() as $row) {
			if ($row['path'] == $path && $row['timestamp'] > time()-self::$minimum_queue_delay ) {
				return true;	
			}
		}
	return false;	
	}	



	//get the database table share.
	private static function db_get_share(){
		$query=OC_DB::prepare("SELECT * FROM `*PREFIX*share` ");
		$result=$query->execute();
		
		while($row=$result->fetchRow()) {
			$rtn[] = $row;
		}
		return $rtn;
	}


	/*
	* Evaluate path and return frist sharing parent
	*/
	private static function get_first_sharing_in($path){
		$splits = explode("/", $path);

		foreach ($splits as $file_name) {
			if ( self::db_folder_is_shared_with_me("/".$file_name) ) {
				//\OCP\Util::writeLog('mailnotify', basename($path).' have been found to be shared under '.$file_name, \OCP\Util::WARN);
				return $file_name;
			}
		}	
		return -1;
	}





//=================== DATABASE ACCES ===================================//




	/**
	 * bool folder shared with me
	 * format: /examplefolder
	 */ 
	private static function db_folder_is_shared_with_me($path,$user = ''){
		$path = str_replace('/Shared/', '/', $path); //TODO REGEX for shared at the begining only!
		
		if ($user = '' ) {
		$user = OCP\User::getUser();			
		}

		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `file_target` = ? AND (`share_with` = ? OR `uid_owner` = ?)');
		$result=$query->execute(array($path,$user,$user));

		if(OC_DB::isError($result)) {
			\OCP\Util::writeLog('mailnotify', 'database error at '.__LINE__ .' Result='.$result, \OCP\Util::ERROR);
			return -1;
		}
		
			\OCP\Util::writeLog('mailnotify', 'database  '.$path.$result->numRows()  , \OCP\Util::ERROR);
		if($result->numRows() > 0){			
			return true;
		}else{
			return false;
		}
	}



	/**
	 * Inserts an upload entry in our mail notify database
	 */
	private static function db_insert_upload($uid,  $timestamp, $path){
		
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_uploads`(`uid`, `timestamp`, `path`) VALUES(?,?,?)');
		$result=$query->execute(array($uid, $timestamp, OC\Files\Filesystem::getLocalFile($path)));
	
		if (OC_DB::isError($result) ) {
			\OCP\Util::writeLog('mailnotify', 'Failed to add new notification in the notify database Result='.$result, \OCP\Util::ERROR);
		}	
		return $result;
	}



	/**
	 * Put nm_upload table into an array
	 */
	private static function db_get_nm_upload(){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_uploads`');
		$result=$query->execute();

		if(OC_DB::isError($result)) {
			\OCP\Util::writeLog('mailnotify', 'Failed to get nm_upload from database at line '.__FILE__.' Result='.$relult, \OCP\Util::ERROR);
			return -1;
		}else{
			$strings = array();
			while($row=$result->fetchRow()) {
				$strings[]=$row;
				}
			return $strings;
		}
	}
	


	/**
	* Remove uploads by path
 	*/
	private static function db_remove_all_nmuploads_for($path){
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_uploads` WHERE `path` = ?');
		$query->execute(array(OC\Files\Filesystem::getLocalFile($path)));
	}
		


	/**
	 * Remove notification disable entry form database   
	 * @param $uid uid if the requesting user. 
	 * @param $path of the file request. 
	 * @return  1 if succes, 0 if fail. 
	 */
	public static function db_remove_user_setting($uid, $path){
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `path` = ?');
		if($query->execute(array($uid, OC\Files\Filesystem::getLocalFile($path)))){
			return 1;
		}
		return 0;
	}


	/**
	 * Add a notification disable entry in the database 
	 * @param $uid uid if the requesting user. 
	 * @param $path of the requested file. 
	 * @return  1 if succes, 0 if fail. 
	 */
	public static function db_user_setting_disable($uid, $path)
	{
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_usersettings`(`uid`, `path`, `value`) VALUES(?,?,?)');
		if($query->execute(array($uid, OC\Files\Filesystem::getLocalFile($path), 'disable'))){
			return 1;
		}
		return 0;
	}



	/**
	 * Get user's notification preferances status for a file/folder.  
	 * @param $uid uid if the requesting user. 
	 * @param $path of the requested file. 
	 * @return [enable|disable|notShared] or 0 if fail
	 */
	public static function db_user_setting_get_status($uid, $path){
		$path = urldecode($path);		
		\OCP\Util::writeLog('mailnotify', '==='.$path, \OCP\Util::ERROR);
	
		if(self::get_first_sharing_in($path) !== -1){
			$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `path` = ?');
			$result=$query->execute(array($uid, OC\Files\Filesystem::getLocalFile($path)));

			if($result->numRows() == 0){
				return 'enable'; 
			}else{
				return 'disable';
			}
		}else{
			return 'notShared';
		}
		return 0;
		
	}
	
	

	/**
	 * Get email address of userID
	 */
	private static function db_get_mail_by_user($uid)
	{
		$key = 'email';
		$query=OC_DB::prepare('SELECT `configvalue` FROM `*PREFIX*preferences` WHERE `configkey` = ? AND `userid`=?');
		$result=$query->execute(array($key, $uid));
		if(OC_DB::isError($result)) {
			return;
		}

		$row=$result->fetchRow();
		$mail = $row['configvalue'];

		return $mail;

	}


//===================== INIT FUNCTIONS ==========================//
//TODO Put this on a seperate file and class 

	/**
	 * Write data to an INI file
	 * 
	 * The data array has to be like this:
	 * 
	 *  Array
	 *  (
	 *      [Section1] => Array
	 *          (
	 *              [key1] => val1
	 *              [key2] => val2
	 *          )
	 *      [Section2] => Array
	 *          (
	 *              [key3] => val3
	 *              [key4] => val4
	 *          )    
	 *  )
	 *
	 * @param string $filePath
	 * @param array $data
	 */
	 
	 
	 
	public static function ini_write($file, array $data)
	{
	    $output = '';

	    $dir = dirname(__FILE__);
		$filePath = $dir."/".$file;

	 
	    foreach ($data as $section => $values)
	    {

	        if (!is_array($values)) {
	            continue;
	        }
	 	
	        //add section
	        $output .= "[$section]\n";
	 
	        //add key/value pairs
	        foreach ($values as $key => $val) {
	            $output .= $key."=".$val."\n";

	        }

	        $output .= "\n";
	    }
	 
	    unlink($filePath);
	    if(!file_put_contents($filePath, trim($output))){
	    	//print("failure");
	    }
	}
	 
	 
	/**
	 * Read and parse data from an INI file
	 * 
	 * The data is returned as follows:
	 * 
	 *  Array
	 *  (
	 *      [Section1] => Array
	 *          (
	 *              [key1] => val1
	 *              [key2] => val2
	 *          )
	 *      [Section2] => Array
	 *          (
	 *              [key3] => val3
	 *              [key4] => val4
	 *          )    
	 *  )
	 * 
	 * @param string $filePath
	 * @return array|false
	 */
	 
	 
	 
	public static function ini_read($file)
	{
		$dir = dirname(__FILE__);
		$filePath = $dir."/".$file;

	    if (!file_exists($filePath)) {
	        return false;
	        
	    }
	 	
	    //read INI file linewise
	    $lines = array_map('trim', file($filePath));
	    $data  = array();
	    	 
	    $currentSection = null;
	    foreach ($lines as $line)
	    {
	    	

	        if (substr($line, 0, 1) == '[') {
	            $currentSection = substr($line, 1, -1);
	            $data[$currentSection] = array();

	        }
	        else
	        {
	        		        	
	            //skip line feeds in INI file
	            if (empty($line)) {
	                continue;
	            }
	 
	            //if no $currentsection is still null,
	            //there was missing a "[<sectionName>]"
	            //before the first key/value pair
	            if (null === $currentSection) {
	                return false;
	            }
	            
	 
	            //get key and value
	            list($key, $val) = explode('=', $line);
	            $data[$currentSection][$key] = $val;
	        }
	    }
	 
	    return $data;
	}



}
