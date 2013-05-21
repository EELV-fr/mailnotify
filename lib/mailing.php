<?php

/**
* ownCloud - MailNotify Plugin
*
* OC_MailNotify_Mailing Class
*
*/
$action = '';
$action_gid = '';


class OC_MailNotify_Mailing {

	/**
	 * Settings
	 */

	// do not notify for following folders
	public static $no_notify_folders = array('fakeGroup1','fakeGroup2');


	public function OC_MailNotify_Mailing(){
		
	}
	/**
	 * Main hooked function
	 */

	public static function main($path){

			// app path
			$app_path = getcwd()."/apps/mailnotify/";

			// username
			$user = OCP\User::getUser();

			$timestamp = time();

			//db part
			$gid = self::db_get_group_by_path($path['path']);
			if(self::db_insert_upload($user, $path['path'], $timestamp, $gid)){
				
			}
			else{
				
			}
			
			// for cronjob too
			self::db_notify_group_members();

	}

	/**
	 * Remove user from settings
	 */

	public static function db_remove_user_setting($uid, $gid)
	{
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `group` = ?');
		if($query->execute(array($uid, $gid))){
			return true;
		}
		return false;
	}

	public static function db_add_user_setting($uid, $gid)
	{
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_usersettings`(`uid`, `group`, `value`) VALUES(?,?,?)');
		if($query->execute(array($uid, $gid, '1'))){
			return true;
		}
		return false;
	}

	/**
	 * Is disabled for group
	 */

	public static function db_is_disabled_for_group($uid, $gid){
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

		//print($path);

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
	 * Inserts an upload entry in our mail notify database
	 */

	public static function db_insert_upload($uid, $path, $timestamp, $gid)
	{
		//if(!OC_Group::groupExists($gid)) { return; }
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_uploads`(`uid`, `path`, `timestamp`, `folder`) VALUES(?,?,?,?)');
		$result=$query->execute(array($uid, $path, $timestamp, $gid));
		return $result;
	}

	/**
	 * Counts the new uploads of a group
	 */	

	public static function db_count_uploads($gid)
	{
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_uploads` WHERE `folder` = ?');
		$result=$query->execute(array($gid));
		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=$row;
		}

		$count = count($strings);
		//print($count); //debug
		return $count;
		
	}

	/**
	 * bool: user disabled notify
	 */

	public static function db_user_disabled_notify($uid, $gid){
		//print($gid);

		$strings = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_usersettings` WHERE `uid` = ? AND `group` LIKE ? AND `value`=2');
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
	 * Get users who uploaded new stuff
	 */

	public static function db_get_upload_users()
	{
		$dec_timestamp = time()-20; //5 min timer 300
		$strings = array();
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_uploads` WHERE `timestamp` <= ? GROUP by folder');
		$result=$query->execute(array($dec_timestamp));

		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=$row;
		}
		
		return $strings;

	}

	/**
	 * notify group members if there are new uploads
	 */
	public static function email($mail,$count,$str_filenames,$folder,$owner){
		
		$l = new OC_L10N('mailnotify');
		$subject = getenv('SERVER_NAME')." - ".$l->t('New upload');
		$from = "MIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1\r\nFrom: ".getenv('SERVER_NAME')." <cloud@".getenv('SERVER_NAME').">\r\n";

		$signature = '<a href="'.OCP\Util::linkToAbsolute('files','index.php').'">'.getenv('SERVER_NAME').'</a>'.'<br>'.$l->t('This e-mail is automatic, please, do not reply to it. If you no longer want to receive theses alerts, disable notification on each shared items.');
		$text = '<html>'.$l->t('There was').' <b>'.$count.'</b> '.$l->t('new files uploaded in').' <a href="'.OCP\Util::linkToAbsolute('files','index.php').'?dir='.$folder.'" target="_blank">'.$folder.'</a> ('.$owner.')<br/><br/>'.$str_filenames.'<br/><p>'.$signature.'</p></html>';
		mail($mail, $subject, $text, $from);	
						
	}
	public static function db_notify_group_members()	{
		$upload_users = self::db_get_upload_users();
		//print_r($upload_users);
		
		foreach($upload_users as $upload_user){
			
			$folder = self::db_get_group_by_path($upload_user['path']);
			
			
			$query=OC_DB::prepare("SELECT * FROM `*PREFIX*share` WHERE `file_target` = ?");
			$result=$query->execute(array('/'.$folder));
			
			//if(OC_DB::isError($result)) {
			//	 OC_DB::getError();
			//return;
			//print($folder);
			$users = array();
			while($row=$result->fetchRow()) {
				$users[] = $row;
			}
			//print_r($users);
			$sent_to_owner = false;
			
			$count = self::db_count_uploads($folder);
			$str_filenames='';
			
			if($count>0){
				$filenames = self::db_get_upload_filenames($folder);

				$str_filenames = '<ul>';
				foreach($filenames as $file){
					$str_filenames .= '<li>
					<a href="'.OCP\Util::linkToAbsolute('files','index.php').'/download'.$file['path'].'" target="_blank">'.basename($file['path']).'</a> 
					<font color="#696969">('.$file['owner'].')</font>
					</li>';
				}
				$str_filenames.='</ul>';			

				foreach($users as $user){
						
						//$uid = OCP\User::getUser();	
						if($sent_to_owner == false and !self::db_user_disabled_notify($user['uid_owner'], $folder)){	
								$mail = self::db_get_mail_by_user($user['uid_owner']);
							
								self::email($mail,$count,$str_filenames,$folder,$user['uid_owner']);
								$sent_to_owner = true;
						}
						$mail = self::db_get_mail_by_user($user['share_with']);	
								
						if($mail != '' and !self::db_user_disabled_notify($user['share_with'], $folder)){						
								self::email($mail,$count,$str_filenames,$folder,$user['uid_owner']);	
						}	
				}
			}
			self::db_remove_uploads_by_group($folder);

		}

	}

	/**
	 * Remove uploads by group/folder
	 */

	public static function db_remove_uploads_by_group($gid)
	{
		$query=OC_DB::prepare('DELETE FROM `*PREFIX*mn_uploads` WHERE `folder` = ?');
		$query->execute(array($gid));
	}


	/**
	 * Get upload filenames by folder
	 */

	public static function db_get_upload_filenames($gid)
	{
		$query=OC_DB::prepare('SELECT * FROM `*PREFIX*mn_uploads` WHERE `folder` = ?');
		$result=$query->execute(array($gid));
		if(OC_DB::isError($result)) {
			return;
		}

		while($row=$result->fetchRow()) {
			$strings[]=array('path'=>$row['path'],'owner'=>$row['uid']);
		}

		/*if(is_array($strings)){
			print_r($strings);
		}*/
		
		//exit();

		//$count = count($strings);
		return $strings;
	}

	/**
	 * Get mail by userID
	 */

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


	/**
	 * Get group by path
	 */

	public static function db_get_group_by_path($path)
	{
		$splits = explode("/", substr($path, 1, strlen($path) ));

		if($splits[0] == 'Shared'){
			//print($splits[1]);
			return $splits[1];
		}else{
			//print($splits[0]);
			return $splits[0];
		}

		/*$count = count($splits);

		if($count != '' and $count != 0)
		{
			return $splits[$count-2];
		}

		return 0;*/

	}


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