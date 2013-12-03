<?php
				
class OC_MailNotify_Hooks{
	
	
	/**
	 *  Notify a new file/forder creation or change.
	 */
	static public function notify($path) {
		return Queue_notification::file_change($path);
		 
	}


	/**
	 *  Notify a new internal_message.
		//array('fromUid' => $msgfrom,'toUid' => $user,'msgContent' => $msgcontent, 'msgFlag' => $msgflag);	
	 */
	static public function notify_IntMsg($params) {
		return	OC_MailNotify_Mailing::email_IntMsg($params['fromUid'], $params['toUid'], $params['msgContent']);
			
	}
}
