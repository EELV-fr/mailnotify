<?php
include_once dirname(dirname(dirname(dirname(__FILE__)))).'/lib/base.php';	
include OC_App::getAppPath('mailnotify').'/lib/mailing.php';




	OC_MailNotify_Mailing::do_notification_queue();
	
	echo " Done-".date("Y-m-d H:i:s").PHP_EOL;