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

	
	 public static function main($path){
		\OCP\Util::writeLog('mailnotify', 'The main() function found at line '.__LINE__.' is depricated. use queue_fileChange_notification() insted', \OCP\Util::WARN);
		self::queue_fileChange_notification($path);
	}
	 

	 
	public static function db_notify_group_members(){
		\OCP\Util::writeLog('mailnotify', 'The db_notify_group_members() function found at line '.__LINE__.' is depricated. use do_notification_queue() insted', \OCP\Util::WARN);
		self::do_notification_queue();
	}	
		

		
	/**
	 * Main hooked function
	 * trigger on file/folder change/upload 
	 */		
	public static function queue_fileChange_notification($path){
		$timestamp = time();

		// check if the file/folder or a parent folder are shared.
		$sharing_parent = self::get_first_sharing_in($path['path']);
		
		if( $sharing_parent !== -1 ){
			// add file/folder to the notifications queue in the database. 
			self::db_insert_upload(OCP\User::getUser(), $path['path'], $timestamp, $sharing_parent);	
		}else{
			\OCP\Util::writeLog('mailnotify', 'Nothing to do. This file/folder is not shared or under a shared directory.', \OCP\Util::WARN);
		}		
	}
	


	/**
	 * direct notification of an internal message (no queue delay)
	 */
	public static function email_IntMsg($fromUid, $toUid, $msg){		
		$l = new OC_L10N('mailnotify');
		$intMsgUrl = OCP\Util::linkToAbsolute('index.php/apps/internal_messages');
		
		$text = "You have a new message from <b>$fromUid</b>.
				<p><br>$msg<br></p>
				Please log in to <a href=\"$intMsgUrl\">%s</a> to reply.<br>";
	
			OC_MailNotify_Mailing::sendEmail($text,$l->t('New message from '.$fromUid),$toUid);
	}

		
	
	// mail all queue notification in the database (mainly triggered by cronjob)
	static public function do_notification_queue(){
			$l = new OC_L10N('mailnotify');
		$nm_upload = self::db_get_nm_upload();
		
		//list all unique nm_upload path
		$filesList = array(); 
		foreach ($nm_upload as $value) {		
			$filesList[$value['path']] = array();
		}
		
		// get each modifiers uids and last edit date of for each file
		foreach ($filesList as $key => $file) {			
			foreach ( $nm_upload as $row) {
				if ( $row['path'] == $key ) {
					$filesList[$key]['uid'] = $row['uid'];
					if ( !isset($file['timestamp']) || $file['timestamp'] < $row['timestamp'] ) {
						$filesList[$key]['timestamp'] = $row['timestamp'];	
					}
				}		
			}
		}
		
		//get who want wich notifications
		$mailTo = array();
		foreach ($filesList as $key => $file) {
			foreach (self::db_get_share() as $row) {
				if ($row['file_target'] == $key && !self::is_uid_exclude($row['uid_owner'],$key) ){					
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
				OC_MailNotify_Mailing::db_remove_all_nmuploads_for($file);//TODO not a good place to be no email send verification 
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

 		$result = OC_Mail::send(
 			OC_MailNotify_Mailing::db_get_mail_by_user($toUid),
		 	$toUid,
		 	'['.getenv('SERVER_NAME')."] - ".$action,
		 	$txtmsg,
		 	'Mail_Notification@'.getenv('SERVER_NAME'),
		 	'',
		 	1,
		 	'',
		 	'',
		 	'',
		 	'' 
		);		
	}
	
	
	
	// check if $path shoud be excluded form $uid notifications.  
	private static function is_uid_exclude($uid,$path){
	
		// hardcoded static exclusion array 
	 	foreach (self::$no_notify_folders as $folder) {
			if ( basename($path) == $folder ) {
				return true;	
			}			 
		 }

		// database user preferance
		// TODO
		
		
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
	 * Inserts an upload entry in our mail notify database
	 */
	private static function db_insert_upload($uid, $path, $timestamp, $gid){
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_uploads`(`uid`, `path`, `timestamp`, `folder`) VALUES(?,?,?,?)');
		$result=$query->execute(array($uid, $path, $timestamp, $gid));
	
		if ( $relult !== 0 ) {
			\OCP\Util::writeLog('mailnotify', 'Failed to add new notification in the notify database Result='.$relult, \OCP\Util::ERROR);
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
		$query->execute(array($path));
	}
		




	
	
//================= UNSORTED  FUNCTIONS ++++++++++++++++++++++++++++++++++





	/**
	 * Remove user from settings
	 */

	public static function db_remove_user_setting($uid, $gid)
	{
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `group` = ?');
		if($query->execute(array($uid, $gid))){
			return 1;
		}
		return 0;
	}

	public static function db_add_user_setting($uid, $gid)
	{
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_usersettings`(`uid`, `group`, `value`) VALUES(?,?,?)');
		if($query->execute(array($uid, $gid, '1'))){
			return 1;
		}
		return 0;
	}

	/**
	 * Is disabled for group
	 */

	public static function db_is_disabled_for_group($uid, $gid){
		$gid = urldecode($gid);

		if(self::db_folder_is_shared($gid)){
			$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `group` = ?');
			$result=$query->execute(array($uid, $gid));
	
			if(OC_DB::isError($result)) {
				return;
			}
			
			$count=$result->numRows();
		
			if($count >= 1){
				return 1; // disabled
			}
			else{
				return -1; // enabled
			}
		}
		else{
			return 2; //not shared
		}
		
	}


	/**
	 * folder is shared
*/

	public static function db_folder_is_shared($path){

		$splits = explode( "/", $path);
		$count = count($splits);
		$path = "/".$splits[$count-1];

		$strings = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `file_target` = ?');
		$result=$query->execute(array($path));

		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=$row;
		}

		$count = count($strings);

		if($count >= 1){
			return true;
		}else{
			return false;
		}

	}

	/**
	 * bool folder shared with me
	 * format: /examplefolder
	 */

	public static function db_folder_is_shared_with_me($folder){

		$user = OCP\User::getUser();

		$strings = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `file_target` = ? AND (`share_with` = ? OR `uid_owner` = ?)');
		$result=$query->execute(array($folder,$user,$user));

		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=$row;
		}

		$count = count($strings);

		if($count >= 1){
			return true;
		}else{
			return false;
		}

	}



	/**
	 * Counts the new uploads of a group
	 */	

	

	/**
	 * bool: user disabled notify
	 */
	public static function db_user_disabled_notify($uid, $gid){
		//print($gid);

		$strings = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `group` LIKE ? AND `value`=1');
		$result=$query->execute(array($uid, '%'.$gid.'%'));
        
		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=$row;
		}

		//print_r($strings);

		$count = $result->numRows();//count($strings);
		//print($count." ");

		if($count >= 1){
			return true;
		}else{
			return false;
		}

	}





	/**
	 * Get mail by userID
	 */
//TODO REWRITE THIS FUNCTION
	public static function db_get_mail_by_user($uid)
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
//TODO SEPERATE FILE FOR THIS 
	/**
	 * Get string between 2 values
	 */

	public static function get_string_between($string, $start, $end){
		$string = " ".$string;
		$ini = strpos($string,$start);
		if ($ini == 0) return "";
		$ini += strlen($start);   
		$len = strpos($string,$end,$ini) - $ini;
		return substr($string,$ini,$len);
	}



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
