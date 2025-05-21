function attach_survey_handler(){

	$("#surveys .icon").click(function(event){
	  event.stopImmediatePropagation();
	});

	$("#surveys .row").click(function (e) {
		var $row=$(this);
		rowid=$(this).attr("id");
		surveyid=rowid.substr(2);
		row_info_id="#"+rowid+'_info';
		row_sel_id="#"+rowid;
		
		//already selected, reset
		if ($row.data('active')==1) {
			$row.removeClass().addClass("row");
			$(row_info_id).removeClass("show").addClass("hide");
			$row.data('active',0);
			return false;
		}
		else{
			//collapse any expanded rows
			$("#surveys .selected").removeClass().addClass("row");
			$("#surveys .show").removeClass("show").addClass("hide");
		}


		//add custom data to active row
		$(row_sel_id).data('active', 1);
		
		$(row_sel_id).toggleClass("selected");
		$(row_info_id).removeClass("hide").addClass("show");
		
		$(row_info_id+" > td").html('<div class="info"><img src="images/loading.gif" alt=""/>'+i18n.js_loading+'</div>');
		
		$.ajax({
			type: "GET",
			url: site_url+"/admin/catalog/survey/"+surveyid,
			cache: false,
			timeout:20000,
			success: function(data) {
				$(row_info_id+" > td").html(data);
			},
			error: function(XMLHttpRequest, textStatus, errorThrow) {
				alert("error");	
				$('#surveys').html('<div class="error">Search failed<br>'+XMLHttpRequest.responseText+'</div>');
			}		
		});
		return false; 
	});

}


function popup_dialog(obj) {
	var settings = {
			centerBrowser:1,
			centerScreen:1,
			height:600,
			left:0,
			location:0,
			menubar:0,
			resizable:1,
			scrollbars:1,
			status:0,
			width:800,
			windowName:$(obj).attr("id"),
			windowURL:$(obj).attr("href"),
			top:0,
			toolbar:0
		};
		var windowFeatures =    'height=' + settings.height +
								',width=' + settings.width +
								',toolbar=' + settings.toolbar +
								',scrollbars=' + settings.scrollbars +
								',status=' + settings.status + 
								',resizable=' + settings.resizable +
								',location=' + settings.location +
								',menuBar=' + settings.menubar;
		
		if ($.browser.msie) {//IE hack
			centeredY = (window.screenTop - 120) + ((((document.documentElement.clientHeight + 120)/2) - (settings.height/2)));
			centeredX = window.screenLeft + ((((document.body.offsetWidth + 20)/2) - (settings.width/2)));
		}else{
			centeredY = window.screenY + (((window.outerHeight/2) - (settings.height/2)));
			centeredX = window.screenX + (((window.outerWidth/2) - (settings.width/2)));
		}

		window.open(settings.windowURL, settings.windowName, windowFeatures+",left="+centeredX+",top="+centeredY).focus();
		return false;
}


jQuery(document).ready(function(){
	//checkbox select/deselect
	$(document.body).on("click","#chk_toggle", function (e){
		$('#surveys .chk').each(function(){ 
			this.checked = (e.target).checked; 
		}); 
	});
	$(document.body).on("click",".chk", function (e){
	   if (this.checked==false){
		$("#chk_toggle").attr('checked', false);
	   }			   
	});			

	$(document.body).on("click","#batch_actions_apply", 
		function (e){
			if( $("#batch_actions").val()=="delete"){
				batch_delete();
			}
			else if ($("#batch_actions").val()=="transfer"){
				batch_transfer_ownership();
			}
			else if ($("#batch_actions").val()=="publish"){
				batch_publish(1);
			}
			else if ($("#batch_actions").val()=="unpublish"){
				batch_publish(0);
			}
		}
	);
	
	//attach_hover();
	attach_survey_handler();

	//page change
	$(document.body).on("change","#pagesize", function (e){
		$("#ps").val($("#pagesize").val());
		$('#form_filter').submit();
	});	
});

function batch_delete(){
	if ($('.chk:checked').length==0){
		alert(i18n.no_item_selected);
		return false;
	}
	if (!confirm(i18n.confirm_delete))
	{
		return false;
	}
	selected='';
	$('.chk:checked').each(function(){ 
		if (selected!=''){selected+=',';}
        selected+= this.value; 
     });
	
	$.ajax({
		timeout:1000*120,
		cache:false,
        dataType: "json",
		data:{ submit: "submit"},
		type:'POST', 
		url: CI.base_url+'/admin/catalog/delete/'+selected+'/?ajax=true',
		success: function(data) {
			if (data.success){
				location.reload();
			}
			else{
				alert(data.error);
			}
		},
		error: function(XHR, textStatus, thrownError) {
			alert("Error occured " + XHR.status);
		}
	});	
}

function batch_transfer_ownership()
{
	if ($('.chk:checked').length==0){
		alert(i18n.no_item_selected);
		return false;
	}
	selected='';
	$('.chk:checked').each(function(){ 
		if (selected!=''){selected+=',';}
        selected+= this.value; 
     });
	
	window.location= CI.base_url+'/admin/catalog/transfer/'+selected;	
}

function batch_publish(publish)
{
	if ($('.chk:checked').length==0){
		alert(i18n.no_item_selected);
		return false;
	}
	selected='';
	$('.chk:checked').each(function(){ 
		if (selected!=''){selected+=',';}
        selected+= this.value; 
     });

	window.location= CI.base_url+'/admin/catalog/publish/'+selected+'/'+publish;	
}



function share_ddi(e,surveyid)
{
	share=0;
	if ($("#"+e.id).is(':checked')==true) {share=1;}
	url=CI.base_url+'/admin/catalog/shareddi/'+surveyid+'/'+share;
	$.get(url);
}

function attach_note(sid,type)
{
	var note_text="";
	if (type=="reviewer")
	{
		note_text=$("#reviewer_note_"+sid).val();	
	}
	else
	{
		note_text=$("#admin_note_"+sid).val();
	}

	$.ajax({
		timeout:1000*120,
		cache:false,
        dataType: "json",
		data:{ note: note_text},
		type:'POST', 
		url: CI.base_url+'/admin/catalog/attach_note/'+sid+'/'+type,
		success: function(data) {
			if (data.success){
				alert(data.success);
			}
			else{
				alert(data.error);
			}
		},
		error: function(XHR, textStatus, thrownError) {
			alert("Error occured " + XHR.status);
		}
	});	
}
