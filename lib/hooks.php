<?php

class OC_MailNotify_Hooks{

	static public function notify($path) {
		OC_MailNotify_Mailing::main($path);
		return true;
	}


}

