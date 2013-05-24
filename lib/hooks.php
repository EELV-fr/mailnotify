<?php

class OC_MailNotify_Hooks{
	
	
	/**
	 *  Notify a new file/forder creation or change.
	 */
	static public function notify($path) {
		OC_MailNotify_Mailing::main($path);
		return true;
	}


	/**
	 *  Notify a new internal_message.
		//array('fromUid' => $msgfrom,'toUid' => $user,'msgContent' => $msgcontent, 'msgFlag' => $msgflag);	
	 */
	static public function notify_IntMsg($params) {
			OC_MailNotify_Mailing::email_IntMsg($params['fromUid'], $params['toUid'], $params['msgContent']);
			return true;
	}
}