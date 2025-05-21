$(document).ready(function () { 
	
	//show/hide variable info
	$(document.body).on("click",".table-variable-list .row-color1, .table-variable-list .row-color2, .table-variable-list .row", function(){ 
		if($(this).attr("id")!=''){
			get_variable($(this).attr("id"));
		}
		return false;
	});

});	

function get_variable(id)
{
	//panel id
	var pnl="#pnl-"+id;
	
	//collapse
	if ($("#"+id).is(".pnl-active")){
		$("#"+id).toggleClass("pnl-active");
		$(pnl).parent().hide();
		return;
	}

	//unset any active panels
	$('.table-variable-list tr').removeClass("pnl-active");
		
	//expand
	ajax_error_handler('pnl-'+id);
	url=CI.current_section+'/variable/'+id;

	//hide any open panels
	$('.var-info-panel').hide();
	
	//show/hide panel
	$("#"+id).toggleClass("pnl-active");
	$(pnl).parent().show();
	$(pnl).html('<img src="images/loading.gif" border="0"/> '+ CI.js_loading);
	$(pnl).load(url+'?ajax=true', function(){
		var fooOffset = jQuery('.pnl-active').offset(),
        destination = fooOffset.top;
	    $('html,body').animate({scrollTop: destination-50}, 500);
	})
}

//show/hide resource
function toggle_resource(element_id){
	$("#"+element_id).toggle();
}

function ajax_error_handler(id)	
{
	$.ajaxSetup({
		error:function(XHR,e)	{
			$("#"+id).html('<div class="error">'+XHR.responseText+'</div>');
		}				
	});	
}
