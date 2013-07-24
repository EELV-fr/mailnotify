$(document).ready(function() {
  if (typeof FileActions !== 'undefined') {
    
	    FileActions.register('all', t('mailnotify','Notify'), OC.PERMISSION_READ, 
	    	function (file) {
	    		var filepath = $('#dir').val() + '/' + file;
	    		$('tr').filterAttr('data-file', String(file)).hover(function(){
	    			if($(this).data('state')==undefined){   
	    				$.post(OC.filePath('mailnotify', 'ajax','action.php'), {action:'isDisabled',action_gid:filepath}, function(disabled) {
			    			var path_img = 'mail.png';
			    			var state='active';
			    			if(disabled == '1'){	
								path_img = 'mail2.png';
								state = 'inactive';
								
							}else if(disabled == '2'){
								path_img = 'mail3.png';
								state = 'notshared';
			
							}
							var row = $('tr').filterAttr('data-file', String(decodeURIComponent(file)));
							$(row).attr('data-state', state);
							$(row).find('a').filterAttr('data-action', t('mailnotify','Notify')).find('img').attr('src', OC.imagePath('mailnotify',path_img));
			    		});
	    			} 
		    	});   		
	    			
				return OC.imagePath('mailnotify', 'mail3.png');	      
		    }, 
		    function (file) {	    	
			    var row = $('tr').filterAttr('data-file', String(file));
		  		var ele_fileNotify = $(row).find('a').filterAttr('data-action',t('mailnotify','Notify'));
		  		var currentstate = $(row).attr("data-state");
		  		var dir = $('#dir').val();
		  		if(dir != '/'){
		  			folder = dir+"/"+file;
		  		}else{
		  			folder = "/"+file;
		  		}
		  		ChangeState(folder, currentstate, ele_fileNotify);	  
	   	 });
	}
});
function ChangeState(folder, currentstate, that){
				
		  	if(currentstate == 'notshared'){
		  		return 0;
		  	}
	  		else if(currentstate == 'active'){
				$.ajax({
				  type: "POST",
				  url: OC.filePath('mailnotify', 'ajax','action.php'),
				  data: { action:'add',action_gid:folder},
				  success: function(retour){
				  	if(retour=='1'){
						$(that).find('img').attr('src', OC.imagePath('mailnotify', 'mail2.png'));
			  			$(that).parent().parent().parent().parent().attr('data-state', 'inactive');
		  			}
				  }
				});

	  		}else{
				$.ajax({
				  type: "POST",
				  url: OC.filePath('mailnotify', 'ajax','action.php'),
				  data: { action:'remove',action_gid:folder},
				  success: function(retour){
				  	if(retour=='1'){
				  		$(that).children('img').attr('src', OC.imagePath('mailnotify', 'mail.png'));
		  				$(that).parent().parent().parent().parent().attr('data-state', 'active');
				  	}
					
				  }
				});
				
	  		}
}
