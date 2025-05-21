//overlay to block/unblock search form
function block_search_form(block){
	if (block==false){$('#search_form').unblock();return false;}
	if ($(".blockUI").is(':visible')){return;}
	$('#search_form').block({ 
		message: '',//'<img src="images/loading.gif" border="0"/> ' + i18n.searching + ' <input type="button" value="'+i18n.cancel+'" onclick="block_search_form(false);"/>', 
		css: { padding:'10px',top:'30px',width:'300px', background:'#F9F9F9', border: '1px solid white' } ,	
		centerX: true, centerY: false,
		overlayCSS:  {backgroundColor: '#fff', opacity:0.7}
    }); 
}


//create history state
function hash_changed(){
	$.bbq.pushState(get_search_state());
}

function get_search_state(){
	
	if($("#vk").val()==''){
		$("#view").val('s');
	}
		
	var result={
			view:$("#view").val(),
			sk:$("#sk").val(),
			vk:$("#vk").val(),
			from:$("#from").val(),
			to:$("#to").val(),
			page:$("#page").val(),
			ps:$("#ps").val(),
			sort_order:$("#sort_order").val(),
			sort_by:$("#sort_by").val(),
			sid:$("#sid").val(),
			_r:$("#_r").val() //needed only for search button
			};

	//countries,data access, topics, collections
	filters=new Object();
	filters.countries={items:$(".filter-by-country input.chk:checked"),name:'country'};
	filters.dtypes={items:$(".filter-by-dtype input.chk:checked"),name:'dtype'};
	filters.topics={items:$(".filter-by-topic input.chk:checked"),name:'topic'};
	filters.collections={items:$(".filter-by-collection input.chk:checked"),name:'collection'};

	for(var filter in filters){
		var matches=[];
		filters[filter].items.each(function() {
			matches.push($(this).val()); 
		});
		result[filters[filter].name]=matches.join(',');
	}

	return result;
}

//build and perform search
function advanced_search()
{	
	if ($("#from").val() > $("#to").val()){ 
		alert(i18n.invalid_year_range_selected); 
		return false;
	}
	
	//topics
	selected_topics=$("#search_form .chk-topic:checked").length;
	total_topics=$("#search_form .chk-topic").length;
	
	//countries
	selected_countries=$("#search_form .chk-country:checked").length;
	total_countries=$("#search_form .chk-country").length;
	
	//remove topics/countries from posting
	if (selected_topics==total_topics && selected_countries==total_countries) {		
		data=$("#search_form :not(.chk-topic, .chk-topic-hd, .chk-country, .chk-country-hd)").serialize();
	}
	else if (selected_countries==total_countries) {
		data=$("#search_form :not(.chk-country, .chk-country-hd)").serialize();
	}
	else if (selected_topics==total_topics) {
		data=$("#search_form :not(.chk-topic, .chk-topic-hd)").serialize();
	}
	else{
		data=$("#search_form").serialize();	
	}

	data+='&sort_order='+$("#sort_order").val()+'&sort_by='+$("#sort_by").val();
	$("#link_export").prop("href",CI.base_url+"/catalog/export/?"+data);


	block_search_form(true);
	$("#surveys").html('<i class="fa fa-spinner fa-pulse fa-2x fa-fw"></i>' + i18n.searching );
	
	$.ajax({
        type: "GET",
        url: CI.base_url+"/catalog/search",
        data: data,
        cache: false,
		timeout:30000,
		success: function(data) {
            $("#surveys").html(data);
			window.search_cache[ $.param.fragment() ]=$("#surveys").html();			
			bindBehaviors(this);
			block_search_form(false);
        },
		error: function(XMLHttpRequest, textStatus, errorThrow) {
			$("#surveys").html('<div class="error">Search failed<br>'+XMLHttpRequest.responseText+'</div>');
			block_search_form(false);
        }		
    });
    return false;        
}


//TODO: may be use .ON for binding instead
function bindBehaviors(e)
{
	//show/hide study sub-variable search
	$(".vsearch").unbind('click').click(function(event) {
		$(this).parent().toggleClass("expand");
		var result=$(this).parent().find(".vsearch-result");
		if (result.html()!='' ){
			result.empty().hide();
			//result.parent().find(".open-close").prop("src",'images/next.gif');
		}
		else{
			result.show().html(i18n.loading).load($(this).prop("href"), function(data){
				//attach click event handler to the variable list links
				
				$(".variables-found .vsearch-result .link").click(function(e){
					var row=$(this).closest("tr");
					window.simple_dialog("dialog_id",row.attr("data-title"),$(this).attr("href"));
					event.stopPropagation();
					return false;
				});
				
				//attach compare handlers
				variable_compare_handlers();
			});
			//result.parent().find(".open-close").prop("src",'images/arrow_down.gif');
		}
		compare_var_summary();
		return false;
	})
			
	//attach variable compare handlers
	variable_compare_handlers();
	
	//on page size change
	$(".switch-page-size .button, .switch-page-size .nada-btn").unbind('click').click(function(event) {
		$("#ps").val($(this).html());
		$("#page").val(1);
		createCookie('ps',$("#ps").val(),1);
		hash_changed();
	});
	
	//page navigation
	$(".nada-pagination a.page-link").unbind("click").click(function(event){
		$("#page").val( $(this).attr("data-page") );
		hash_changed();
		return false;
	});
	
	//show/hide search tokens
	if ($(".active-filters").children().length>0){
		$(".active-filters").show();
	}
	else{
		$(".active-filters").hide();
	}
}


function compare_var_summary(){
		var sel_items=readCookie("variable-compare");
		
		if(sel_items==null || sel_items==''){
			sel_items=Array();
		}
		else{
			sel_items=sel_items.split(",");
		}
								
		//get unique study count
		var studies=[];
		for (var i = 0; i < sel_items.length; i++) {
			if(sel_items[i].indexOf("/") !== -1){
				var item=sel_items[i].split("/");
				if($.inArray(item[0], studies)==-1){
					studies.push(item[0]);
				}
			}
		}//end-for
		
		if(sel_items.length==0){
			$(".variables-found .var-compare-summary").html( i18n.js_compare_variable_select_atleast_2);
		}
		else{				
			$(".variables-found .var-compare-summary").html( sel_items.length + " " + i18n.js_compare_variables_selected + " " + studies.length + " " + i18n.js_compare_studies_selected);
		}
}

function update_compare_variable_list(action,value){
	var sel_items=readCookie("variable-compare");
	
	if(sel_items==null || sel_items==''){
		sel_items=Array();
	}
	else{
		sel_items=sel_items.split(",");
	}

	switch(action)
	{
		case 'add':
			if($.inArray(value, sel_items)==-1){
				sel_items.push(value);
			}
			break;
		
		case 'remove':
			var index_matched=$.inArray(value, sel_items);
			if(index_matched>-1){
				sel_items.splice(index_matched,1);
			}			
			break;
		
		case 'remove-all':
			eraseCookie("variable-compare");return;
		break;
	}
	
	//update cookie
	createCookie("variable-compare",sel_items,1);
}

//compare checkbox click
function variable_compare_handlers(){
	
	//compare button
	$('.btn-compare-var').unbind('click').click(function(event) {
	 	event.stopPropagation();
		var sel_items=readCookie("variable-compare");
		
		if(sel_items==null){
			sel_items=Array();
		}
		else{
			sel_items=sel_items.split(",");
		}
		if(sel_items.length>1){
			window.open(CI.base_url+'/catalog/compare','compare');
		}
		else{
			alert(i18n.js_compare_variable_select_atleast_2);return false;
		}
		return false;
	});
	
	
	$('.compare').unbind('click').click(function(event) {
		var sel_items=readCookie("variable-compare");
		
		if(sel_items==null){
			sel_items=Array();
		}
		else{
			sel_items=sel_items.split(",");
		}

		if ($(this).prop("checked")){
			update_compare_variable_list('add',$(this).val());
		}
		else{
			update_compare_variable_list('remove',$(this).val());
		}
		
		compare_var_summary();
	});
	
	//disable even propogations for compare link
	$(".var-quick-list .compare-variable").unbind('click').click(function(event) {		
			event.stopPropagation();
	});
}

//change page view - study or variable
function change_view(value){
	if ($("#view").val()==value){return false;}
	$("#view").val(value);$("#page").val(1);
	
	//reset sort
	$("#sort_order").val("");
	$("#sort_by").val("");

	hash_changed();
}


$(document).ready(function() 
{	
	//reset page if any form values change; must appear before any change handlers on the form
	$("#search_form :input").change(function() {
		$("#page").val(1);
	});

	//years
	$('#from, #to').change(function(event) {
		if ($("#from").val() > $("#to").val()){ 
			alert(i18n.invalid_year_range_selected); return false;
		}
		hash_changed();
	});
	
	//filter checkbox any or specific item click: applies to country, topic
	$("#search_form .items-container .item :checkbox").click(function(event) {
		//uncheck ANY option
		$(this).closest(".filter-box").find(".chk-any").prop("checked",false);
		hash_changed();
	});	
	
	//ANY checkbox click handler. unset all other options
	$("#search_form .filter-box .any :checkbox").click(function(event) {
		$(this).closest(".filter-box").find(".items-container").find(".chk").prop("checked",false);
		hash_changed();
	});
	
	//search button
	$("#btnsearch").click(function() {
    	$("#page").val(1);
		$("#_r").val($.now());

        //set default sort to rank if searching on keywords
        if ($.trim($("#sk").val())!='' ){
            $("#sort_by").val("rank");
            $("#sort_order").val("desc");
        }
        else{//reset to default sort only if sort_by is set to "rank"
            if ($("#sort_by").val()=="rank") {
                $("#sort_by").val("nation");
                $("#sort_order").val("asc");
            }
        }
        
		hash_changed();return false;
	});

	//sk/vk reset button
	$("#reset").click(function() {
    	$("#page").val(1);
		$("#sk").val("");
		$("#vk").val("");
		hash_changed();return false;
	});

	//search general help icon
	$(".filter-box .keyword-help img").click(function() {
		window.simple_dialog("dialog_id",$(this).attr("title"),$(this).attr("data-url"));
	});

	//DA help icon
	$(".filter-box .da-help img").click(function() {
		window.simple_dialog("dialog_id",$(this).attr("title"),$(this).attr("data-url"));
	});


	var page_size=readCookie('ps');
	if(page_size==null || page_size=='' ){
		$("#ps").val(page_size);
	}


	bindBehaviors();
});

function search_page(num){
	$("#page").val(num);advanced_search();	
}


//hashchange event handler
$(window).bind( 'hashchange', function(e) {

	fragments=$.deparam.fragment();
	var found=false;
	filters=new Object();
	
	if(typeof fragments.country != 'undefined'){
		filters.country={
			items:		fragments.country,
			container:	'#search_form .filter-by-country'
		};
	}

	if(typeof fragments.dtype != 'undefined'){
		filters.dtype={
			items: 		fragments.dtype,
			container: 	'#search_form .filter-by-dtype'
		};
	}
	
	if(typeof fragments.collection != 'undefined'){
		filters.collection={
			items: 		fragments.collection,
			container: 	'#search_form .filter-by-collection'
		};
	}

	if(typeof fragments.topic != 'undefined'){
		filters.topic={
			items: 		fragments.topic,
			container: 	'#search_form .filter-by-topic'
		};
	}

	for(var filter in filters){
		found=false;
		var selected_items=filters[filter].items.split(",");
		
		//iterate each checkbox and compare if it is one of the selected
		$(filters[filter].container).find(".items-container .chk").each(function() { 
			if ($.inArray($(this).prop('value'), selected_items)!==-1 ){
				$(this).prop('checked',true);
				found=true;
			}
			else{
				$(this).prop('checked',false);
			}
		});	
		
		//uncheck/check ANY option
		$(filters[filter].container).find(".any :checkbox").prop("checked",!found);
		
		//show/hide checked/unchecked items
		if(filter=='country' || filter=='collection' || filter=='topic'){

			//show only active checkboxes
			$(filters[filter].container).find(".items-container .chk").each(function() { 
				if($(this).prop("checked")==false){
					$(this).closest(".item").addClass("inactive");
				}
				else{
					$(this).closest(".item").removeClass("inactive");
				}
			});
			
			var selected_chk=$(filters[filter].container).find(".items-container .chk:checked").length;
			var total_chk=$(filters[filter].container).find(".items-container .chk").length;
			
			//scrollbar for country list
			if(selected_chk>10){
				$(filters[filter].container).find(".items-container").addClass("scrollable");
			}
			//else if(selected_items==0){}
			else{
				$(filters[filter].container).find(".items-container").removeClass("scrollable");	
			}
			
			//update summary stats
			if(selected_chk==total_chk || selected_chk==0){
				$(filters[filter].container).find(".selected-items-count").html(total_chk);
			}
			else if (selected_chk<total_chk){
				$(filters[filter].container).find(".selected-items-count").html(selected_chk + ' / ' + total_chk);
			}

		}//end-if
		
	}//end-for
	
	//years
	if(typeof fragments.from != 'undefined'){
		$("#from option[value='"+fragments.from+"']").prop('selected', 'selected');
	}
	if(typeof fragments.to != 'undefined'){
		$("#to option[value='"+fragments.to+"']").prop('selected', 'selected');
	}

	//keywords
	if(typeof fragments.sk != 'undefined'){
		$("#sk").val(fragments.sk);
	}
	if(typeof fragments.vk != 'undefined'){
		$("#vk").val(fragments.vk);
	}
	
	//sid
	if(typeof fragments.sid != 'undefined'){
		$("#sid").val(fragments.sid);
	}
	
	//page
	if(typeof fragments.page != 'undefined'){
		$("#page").val(fragments.page);
	}
	
	//view
	if(typeof fragments.view != 'undefined'){
		$("#view").val(fragments.view);
	}
	
	//page size
	if(typeof fragments.view != 'undefined'){
		$("#ps").val(fragments.ps);
	}
	
	//sort
	var sort_fields=["title","nation","year","popularity"];
	var sort_order=["asc","desc"];
	
	if(typeof fragments.sort_by!= 'undefined' && $.inArray(fragments.sort_by,sort_fields)> -1 ){
		$("#sort_by").val(fragments.sort_by);
	}

	if(typeof fragments.sort_order!= 'undefined' && $.inArray(fragments.sort_order,sort_order)> -1 ){
		$("#sort_order").val(fragments.sort_order);
	}
		
	var fragment_str = $.param.fragment();
	if ( window.search_cache[ fragment_str ] ) {
		$("#surveys").html(window.search_cache[ fragment_str ]);
		bindBehaviors();
	}
	else {	
		if ($.param.fragment()!==""){
			advanced_search();
		}
	}
})


$(document).ready(function()  {
	//global search cache
	window.search_cache={};
	//trigger hashchange event on page load
	$(window).trigger( 'hashchange' );	
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	// simple dialog
	/////////////////////////////////////////////////////////////////////////////////////////////
	window.simple_dialog=function simple_dialog(dialog_id,title,data_url)
	{
		if($("#"+dialog_id).length ==0) {
            /********* for jQuery ui modal *******/

            /* $("body").append('<div id="'+dialog_id+'" title="'+title+'">loading...</div>'); // for jQuery ui modal

            var dialog=$( "#"+dialog_id ).dialog({
              height: 520,
              position:"center",
              width:730,
              modal: true,
              autoOpen: false
            });//end-dialog*/

            /********* for Bootstrap modal *********/

            $("body").append('<div class="modal fade" id="'+dialog_id+'" tabindex="-1" role="dialog"  aria-hidden="true">\
                <div class="modal-dialog  modal-lg catalog-modal-dialog" role="document">\
                <div class="modal-content">\
                <div class="modal-header">\
                <h5 class="modal-title" id="'+dialog_id+'Label">'+title+'</h5>\
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">\
						<span aria-hidden="true">&times;</span>\
					</button>\
					</div>\
					<div class="modal-body">\
				</div>\
					<div class="modal-footer">\
						<button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>\
					</div>\
					</div>\
					</div>\
					</div>');




		}
		else
		{
            /********* for jQuery ui modal *******/
			/*dialog=	$("#"+dialog_id);
			dialog.html("loading...");
			dialog.dialog({ title: title});*/

            /********* for Bootstrap modal *********/
            $('#'+dialog_id+' h5.modal-title').html(title);
            $('#'+dialog_id+' div.modal-body').html("loading...");




		}
        // for jQuery ui modal
		/*
		dialog.dialog( "open" ); // for jQuery ui modal
        //$('#'+dialog_id).load(data_url+'?ajax=1');//load content
        */

        // for Bootstrap modal
		$('#'+dialog_id).modal('show');// for Bootstrap modal
        $('#'+dialog_id+' div.modal-body').load(data_url+'?ajax=1');//load content
	}//end function
	
	
	
  	/////////////////////////////////////////////////////////////////////////////////////////////
	// selection filter dialog
	/////////////////////////////////////////////////////////////////////////////////////////////
	window.init_dialog=function init_dialog(dialog_id,title,data_url)
	{
		$("body").append('<div id="'+dialog_id+'" title="'+title+'"></div>');
		
		 var dialog=$( "#"+dialog_id ).dialog({
		  height: 520,
		  position:"center",
		  width:730,
		  modal: true,
		  autoOpen: false,
		  
		  buttons: [
			{
				text: i18n.cancel,
				click: function() {$( this ).dialog( "close" );}
			},
			{
				text: i18n.apply_filter,
				click: function() {
				var dialog=$(this).closest(".ui-dialog");
				var source_list=dialog.data('source-list');			
				var dialog_selection=dialog.find(".container").find(".cnt :checked");
				var values=[];
				//var total_items=source_list.find(".chk").length;//total items available for selection
	
				dialog_selection.each(function() { 
					values.push($(this).val()); 
				});
				
				var found=false;
				source_list.find(".chk").each(function() { 
					if ($.inArray($(this).prop('value'), values)!==-1 ){
						$(this).prop('checked',true);
						found=true;
					}
					else{
						$(this).prop('checked',false);
					}			
				});	
				
				$( this ).dialog( "close" );
				
				if (found) { 
					//uncheck ANY option
					source_list.find(".chk-any").prop("checked",false);
					hash_changed();
				}
				else{
					//check ANY option if nothing selected from the dialog
					source_list.find(".chk-any").prop("checked",true);
					hash_changed();
				}
	
			}//end apply filter
			}
    	]
		  
		});//end-dialog

	//load dialog content
	$('#'+dialog_id).load(data_url+'?repo='+$("#repo_ref").val(), function() {		
		dialog.closest(".ui-dialog").find(".ui-dialog-title").append('<div class="ui-dialog-subtitle"><span class="ui-dialog-stats"></span> | <span class="clear-selection link">'+i18n.clear+'</span></div>');
	});
	
	}//end function

	//find out how many dialogs needs to be created
	//add dialog html to the body
	$(".filter-box .btn-select").each(function(){
		var dialog_id=$(this).attr("data-dialog-id");
		var dialog_title=$(this).attr("data-dialog-title");
		var data_url=$(this).attr("data-url");
		if(typeof dialog_id !== "undefined") {
			init_dialog(dialog_id,dialog_title,data_url);
		}
	});

	//dialag open button click handler
	$(".filter-box .btn-select").click(function(event){		
		var dialog_id=$(this).attr("data-dialog-id");		
		if(typeof dialog_id === "undefined") {return false;}		

		var dialog=$( "#"+dialog_id ).dialog( "open" );
		var source_list=$(this).closest(".filter-box").find(".items-container");		
		var dialog_ui=dialog.closest(".ui-dialog");
		dialog_ui.data('source-list', source_list);
		
		//pre select options on the dialog using values from the source_list
		var source_selection=[];
		source_list.find(".chk:checked").each(function() { 
			source_selection.push($(this).val()); 
		});
			
		var found=false;
			
		dialog_ui.find(".container").find(".col-2-s input,.col-2 input").each(function() { 
			if ($.inArray($(this).val(), source_selection)!==-1 ){
				$(this).prop('checked',true);
				found=true;
			}
			else{
				$(this).prop('checked',false);
			}			
		});
		
		dialog_update_stats(dialog_ui);
	});


	//parent/child selection
	$(document.body).on("click",".ui-dialog .rows-container :checkbox", function(){ 
		var data_type=$(this).attr("data-type");
		var dialog=$(this).closest(".ui-dialog");
		if (data_type=='parent'){
			//select/deselect all children
			var parent_checkbox=$(this);
			$(this).closest(".row").find(":checkbox").each(function() { 
				$(this).prop('checked',parent_checkbox.prop("checked"));
				dialog.find('[value="'+$(this).val()+'"]').prop("checked",parent_checkbox.prop("checked"));
			});
		}
		else if (data_type=='child'){
			//deselect parent
			$(this).closest(".row").find(".parent").prop("checked",false);
			//check/uncheck all other instances of the element
			dialog.find('[value="'+$(this).val()+'"]').prop("checked",$(this).prop("checked"));
		}
		dialog_update_stats(dialog);
	});
	
	
	//sort links
	$(document.body).on("click",".catalog-sort-links a", function(){
		$("#sort_by").val($(this).attr("data-sort_by"));
		$("#sort_order").val($(this).attr("data-sort_order"));
		hash_changed();	return false;
	});	
	
	
	//update dialog selection
	//@cnt count items only with the class=cnt
	function dialog_update_stats(dialog){
		var selected=dialog.find(".container").find(".cnt :checked").length;
		dialog.find(".ui-dialog-stats").html(selected + ' ' + i18n.selected);
	}

	//scrollto options for dialog countries by regions
	$(document.body).on("click",".ui-dialog .container .index span", function(){ 
		$(".ui-dialog .rows-container").scrollTo("#"+$(this).attr("data-id"));
	});

	//dailog reset button	
	$(document.body).on("click",".ui-dialog .clear-selection", function(){ 
		var dialog=$(this).closest(".ui-dialog");
		dialog.find("input:checkbox").prop("checked",false);
		dialog_update_stats(dialog);
	});
	
	//remove search token
	$(document.body).on("click",".active-filters-container .remove-filter", function(){ 
		var type=$(this).attr("data-type");
		var value=$(this).attr("data-value");
		switch(type)
		{
			case 'country':
				$(".filter-box .country-items :checkbox[value="+value+"]").trigger("click");	
				break;
			case 'topic':
				$(".filter-box .topic-items :checkbox[value="+value+"]").trigger("click");	
				break;								
			case 'dtype':
				$(".filter-box .filter-da :checkbox[value="+value+"]").trigger("click");	
				break;				
			case 'collection':
				$(".filter-box .collection-items :checkbox[value="+value+"]").trigger("click");	
				break;				
			case 'years':
				$("#from").val($("#from option:last").val());
				$("#to").val($("#to option:first").val());
				$("#from").trigger("change")				
				break;
			case 'sk':
				$("#sk").val("");hash_changed();
				break;
			case 'vk':
				$("#vk").val("");hash_changed();
				break;
			case 'sid':
				$("#sid").val("");hash_changed();
				break;				
		}
		
	});
	
	//disable propogation of events from children elements on the container
	$(document.body).on("click","#surveys .survey-row a, .variable-list .vrow,.variable-list .vrow .compare,.variable-list", function(event){
			event.stopPropagation();
	});

	//on survey,variable row click
	$(document.body).on("click","#surveys .survey-row, .variable-list .vrow", function(){
			var target='';
			if(typeof $(this).attr("data-url-target") != 'undefined'){
				target=$(this).attr("data-url-target");
			}
			if(target==''){
				window.location=$(this).attr("data-url");
			}
			else{
				window.simple_dialog("dialog_id",$(this).attr("data-title"),$(this).attr("data-url"));return false;
			}			
	});
	
	/*global ajax error handler */
	$( document ).ajaxError(function(event, jqxhr, settings, exception) {
		if(jqxhr.status==401){
			window.location=CI.base_url+'/auth/login/?destination=catalog/';
		}
	});

  });//end-document-ready


//cookie helper functions
//source: http://www.quirksmode.org/js/cookies.html
function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function eraseCookie(name) {
	createCookie(name,"",-1);
}
