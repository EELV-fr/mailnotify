<?php
/**
* ownCloud - MailNotify Plugin
* 
* OC_MailNotify_Mailing Class
*
*/
class OC_MailNotify_Mailing {
	private static $no_notify_folders = array('fakeGroup1','fakeGroup2'); 	// do not notify for following folders	
	private static $minimum_queue_delay = 00;
	private static $cloud_name = 'OwnCloud';	
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
	/** @deprecated */	 
	public static function queue_fileChange_notification($path){
		\OCP\Util::writeLog('mailnotify', 'The queue_fileChange_notification() function found at line '.__LINE__.' is depricated. use lib/Queue_notification.php insted', \OCP\Util::WARN);
	}
	


//==================== PUBLIC ==============================//


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

		self::sendEmail($text,$l->t('New message from '.$fromUid),$toUid);
	}

		
	
	/**
	 *  check for pending notification and send corresponding emails (trigger by cronjob) 
	 * @return void 
	 */
	static public function do_notification_queue(){
		$nm_upload = self::db_get_nm_upload();
		$changes_list = array(array('last_timestamp' => '', 'modifier_uid' => array(), 'shared_with' => array()  ));
		$notification_list = array();	
		//list all unique nm_upload path with recent timestamp and editors list.
		foreach ($nm_upload as $nm_upload_row) {		
			$changes_list[$nm_upload_row['path']]['last_timestamp'] = $nm_upload_row['timestamp'];
			$changes_list[$nm_upload_row['path']]['modifier_uid'][] = $nm_upload_row['uid'];   
		}

		//List interested users foreach changed files .
		foreach ($changes_list as $changed_file_id => $changes_list_row) {
			$changes_list[$changed_file_id]['shared_with'] = array();
			foreach (self::db_get_tree_of($changed_file_id) as $tree_needle) {
				$share_with = self::db_get_shares_with_for($tree_needle); 
				$changes_list[$changed_file_id]['shared_with'] = array_merge($changes_list[$changed_file_id]['shared_with'] , $share_with); 
			}
			
			// create a list of notification by users
			foreach ($changes_list[$changed_file_id]['shared_with'] as $uid_key => $uid_val) {
				if (!self::is_uid_exclude($changed_file_id,$uid_key)){
					$notification_list[$uid_key][$changed_file_id] = $changed_file_id;
				}
			}
		}
						//var_dump($notification_list); echo "<hr>";			
		//assamble emails
		$l = new OC_L10N('mailnotify');
		foreach ($notification_list as $uid => $changed_file_list) {
			$li = '';
			foreach ($changed_file_list as $changed_file_id => $file_row) {
				$file_name = self::db_get_name_of_fileid($changed_file_id);
				$url_path = OCP\Util::linkToAbsolute('files').'?dir='.$changed_file_id; // FIXME path depend on user  
				$li .="<li>  <a href=\"$url_path\" target=\"_blank\" > $file_name </a>  </li>";
				self::db_remove_all_nmuploads_for($changed_file_id);
			}
			$msg = $l->t('Following files have been modified.')."<br><ul>$li</ul>";
			self::sendEmail($msg,$l->t('New upload'),$uid);
		}
	}



//================= PRIVATE ===============================//

	private static function sendEmail($msg,$action,$toUid){
 		$l = new OC_L10N('mailnotify');

		$email = OC_Preferences::getValue($toUid, 'settings', 'email', '');
		$from = OCP\Util::getDefaultEmailAddress('Mail_Notification');
		$txtmsg = '<html><p>Hi, '.$toUid.', <br><br>';
		$txtmsg .= "<p>$msg<p>";
		$txtmsg .= $l->t('This e-mail is automatic, please, do not reply to it.').'</p></html>';
		$serverName =  self::$cloud_name; 	
 				var_dump($email, $toUid, "[$serverName] $action", $txtmsg, $from, 'Owncloud', 1 );echo "<hr>";
 		if ($email !== NULL) {
 			
			try{
	 			OC_Mail::send($email, $toUid, "[$serverName] $action", $txtmsg, $from, 'Owncloud', 1 );
			}catch(Exception $e) {
				\OCP\Util::writeLog('mailnotify', "A problem occurs while sending the e-mail to $toUid at __LINE__", \OCP\Util::WARN);
				return false;			
			}	
		}else{
			\OCP\Util::writeLog('mailnotify', "email adress not found for $toUid at __LINE__", \OCP\Util::WARN);
		 }
	return true;
	}
	
	
	
	// check if $path shoud be excluded form $uid notifications.
	// @return true if shoud be excluded false if not
	private static function is_uid_exclude($file_id,$share_with_uid){
		
		// hardcoded static exclusion array 
	 	foreach (self::$no_notify_folders as $folder) {
			if ( $fileName == '/'.$folder ) {
				return true;
			}			 
		 }
		
		if ( self::db_user_setting_is_disable($share_with_uid, $file_id) ) {
			return true;
		}
	
		//exclude author of change
		$entryfound = 0 ; 
		$isowner = 0 ; 
		foreach (self::db_get_nm_upload() as $row) {
			if ( $file_id == $row['path'] &&  $share_with_uid === $row['uid'] ) {
				return true;
			}		
		}
		
		//ignore if the most recent notification is inside the time buffer 
		foreach (self::db_get_nm_upload() as $row) {
			if ($row['path'] === $file_id && $row['timestamp'] > time()-self::$minimum_queue_delay ) {
				return true;	
			}
		}

	return false;	
	}	


//=================== DATABASE ACCES ===================================//

	private static function db_get_filecash_path($itemId){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE `fileid` = ? ');
		$result = $query->execute(array($itemId));
		
		if(OC_DB::isError($result)) {
			\OCP\Util::writeLog('mailnotify', 'database error at '.__LINE__ .' Result='.$result, \OCP\Util::ERROR);
			return -1;
		}
		while($row=$result->fetchRow()) {
			return $row['path'];
		}
	
	}
	


		private static function db_get_name_of_fileid($fileId){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE `fileid` = ? ');
		$result = $query->execute(array($fileId));
		
		if(OC_DB::isError($result)) {
			\OCP\Util::writeLog('mailnotify', 'database error at '.__LINE__ .' Result='.$result, \OCP\Util::ERROR);
			return -1;
		}
		while($row=$result->fetchRow()) {
			return $row['name'];
		}
		
	}



	private static function db_is_under($needleId,$haystackId){
//OC_Share_Backend_Folder::getChildren 	( 	  	$itemSource	) 	
//http://fossies.org/dox/owncloud-5.0.13/classOC__Share__Backend__Folder.html#afde6f72d2eff5556836dbce1356f9114

		if ($needleId == $haystackId) {return TRUE;}
		
		// get parent id 
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE `fileid` = ? ');
		$result = $query->execute(array($haystackId));
		while($row=$result->fetchRow()) {
			
			if ($row['parent'] != $needleId && $row['parent'] != -1 ) {				
				return self:: is_under($needleId,$row['parent']);
				
			} else if ($row['parent'] == -1 ) {
				return false;
				
			}else {
				return true;
			}		
		}
	}
	
	
	
	private static function db_get_tree_of($file_id){
		$needle_id = $file_id;	
		$list = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE `fileid` = ? ');
		$result = $query->execute(array($needle_id));
		while (	$row=$result->fetchRow() ) {
			$list[] = $needle_id;
			$list = array_merge($list, self::db_get_tree_of($row['parent']));
		}		
		return $list;		
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


	//also add owner and resolve groups
	private static function db_get_shares_with_for($file_id){
	// OCP\Share::resolveReShare 	( 	  	$linkItem	) 	
	
		$query=OC_DB::prepare("SELECT * FROM `*PREFIX*share` WHERE `file_source` = ? ");
		$result=$query->execute(array($file_id));
		$user_list =  array();
		
		while($row=$result->fetchRow()) {
			if(self::db_isgroup($row['share_with'])){
				$user_list = array_merge( $user_list, self::db_get_usersOfGroup($row['share_with']));
			}else{
				$user_list[$row['share_with']] = array_merge( $user_list[$row['share_with']], $row['share_with']);
			}
			$user_list[$row['uid_owner']] = $row['uid_owner'];
		}
		
	return $user_list;
	}

 
	/**
	 * bool folder shared with me
	 * format: /examplefolder
	 */ 
	private static function db_folder_is_shared_with_me($path,$user = ''){
		if ($user == '' ) {
			$user = OCP\User::getUser();			
		}
		
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `item_source` = ? AND (`share_with` = ? OR `uid_owner` = ?)');
		$result=$query->execute(array($path,$user,$user));

		if(OC_DB::isError($result)) {
			\OCP\Util::writeLog('mailnotify', 'database error at '.__LINE__ .' Result='.$result, \OCP\Util::ERROR);
			return -1;
		}
		
		if($result->numRows() > 0){			
			return true;
		}else{
			return false;
		}
	}



	/**
	 * Put nm_upload table into an array
	 */
	private static function db_get_nm_upload(){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_uploads` ORDER BY `timestamp`');
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
	* Remove uploads by file_id
 	*/
	private static function db_remove_all_nmuploads_for($fileId){
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_uploads` WHERE `path` = ?');
		$result=$query->execute(array($fileId));
		if(OC_DB::isError($result)) {
			echo "db_remove_all_nmuploads_for direct remove ERROR";
		}

	// clean forgotten entry in database.
	$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_uploads` WHERE `timestamp` < ?');
		$result=$query->execute(array((self::$minimum_queue_delay*3)+60));
		if(OC_DB::isError($result)) {
			echo "db_remove_all_nmuploads_for database cleaning error ERROR";
		}	
 

	}
		
/*
        * Evaluate path and return frist sharing parent
        */
        private static function get_first_sharing_in($path){                
                $splits = explode("/", $path);
                $shares = self::db_get_share();
        
                foreach ($splits as $file_name) {
                        foreach ($shares as $shares_row) {
                                if ($shares_row['file_target'] == '/'.$file_name ) {
                                        return $file_name;
                                }                                
                        }
                }        
                return -1;
        }
		

	/**
	 * Remove notification disable entry form database
	 * @param $uid uid if the requesting user.
	 * @param $path of the file request.
	 * @return  1 if succes, 0 if fail.
	 */
	public static function db_remove_user_setting($uid, $path){
		$path = self::db_get_id_of($path);
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `path` = ?');
		$result = $query->execute(array($uid, $path));
		
		if(OC_DB::isError($result)) {
			return 0;
		}
		return 1;
	}


	/**
	 * Add a notification disable entry in the database 
	 * @param $uid uid if the requesting user. 
	 * @param $path of the requested file. 
	 * @return  1 if succes, 0 if fail. 
	 */
	public static function db_user_setting_disable($uid, $path)
	{
		$path = self::db_get_id_of($path);	
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_usersettings`(`uid`, `path`, `value`) VALUES(?,?,?)');
		$result = $query->execute(array($uid, $path, 'disable'));
		if(OC_DB::isError($result)) {
			return 0;
		}
		return 1;
	}

 
	/**
	 * Get user's notification preferances status for a file/folder.  
	 * @param $uid uid if the requesting user. 
	 * @param $path of the requested file. 
	 * @return [enable|disable|notShared] or 0 if fail
	 */
	public static function db_user_setting_get_status($uid, $path){
		$path = urldecode($path);
		$file_id = self::db_get_id_of($path);
		if(self::get_first_sharing_in($path) !== -1){
			$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `path` = ?');
			$result=$query->execute(array($uid, $file_id));

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



	private static function db_user_setting_is_disable($uid, $file_id){


		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `path` = ?');
		$result=$query->execute(array($uid, $file_id));
		if($result->numRows() == 0){
			return false; 
		}else{
			return true;
		}
	
	return 0;
	}


	
	private static function db_get_id_of($path){
		$result =  OC_Files::getFileInfo($path); 		
	return $result["fileid"];

	}

	
	
	private static function db_isgroup($gid){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*group_user` WHERE `gid` = ?');
		$result=$query->execute(array($gid));
		 
		 if(OC_DB::isError($result)) {
			return -1;
		 }
		 
		if($result->numRows() > 0){
				return true; 
			}else{
				return false;
			}
	}

		


	private static function db_get_usersOfGroup($gid){
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*group_user` WHERE `gid` = ?');
		$result=$query->execute(array($gid));
		 
		 if(OC_DB::isError($result)) {
			return -1;
		 }
		$users =   array();
	
		while($row=$result->fetchRow()) {				
		
		$users[$row['uid']]=$row['uid'];	
				
		}
		return $users;
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
//     /watch?v=TJL4Y3aGPuA