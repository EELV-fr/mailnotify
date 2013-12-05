<?php



class Queue_notification{
	
	public static function file_change($path){
		$fileInfo = OC_Files::getFileInfo($path['path']);	
 		$timestamp = time();
		self::db_insert_upload(OCP\User::getUser(),  $timestamp,$fileInfo['fileid']);	
	}


private static function db_insert_upload($uid,  $timestamp, $fileid){
		
		$query=OC_DB::prepare('INSERT INTO `*PREFIX*mn_uploads`(`uid`, `timestamp`, `path`) VALUES(?,?,?)');
		$result=$query->execute(array($uid, $timestamp, $fileid));
	
		if (OC_DB::isError($result) ) {
			\OCP\Util::writeLog('mailnotify', 'Failed to add new notification in the notify database Result='.$result, \OCP\Util::ERROR);
		}	
		return $result;
	}

}