<?php
$sid=$this->uri->segment(4);
$selected_page=$this->uri->segment(5);

$study_type=isset($type) ? $type : 'survey';
?>

<script type="text/javascript">
function toggle(element){
	$(element).toggleClass("collapse");
}

$(document).ready(function () {

		$(".collapsible .box-caption").unbind('click');
		$(".collapsible .box-caption").click(function(e){
			toggle_box(this);
			return false;
		});

		$(".collapsible .cancel-toggle").click(function(e){
				reset_box(this);
				return false;
		});

		$(".box .box-header").click(function(e){
			toggle_sidebar(this);
			return false;
		});

		//show/hide remote da url depending on the da form selected
		$("#formid").change(function(e){
			sh_remote_da_link();
		});

		//show/hide da
		sh_remote_da_link();

		//tags
		$("#btn-tag").click(function(e){
			add_tag();
			return false;
		});

		$("#tag").on('keyup',null,function(event){
			if(event.keyCode==13){
				add_tag();
				return false;
			}
		});

		//study publish/unpublish
		$(document.body).on("click","#survey .publish, .survey-publish .publish", function(){
			var studyid=$(this).attr("data-sid");
			if ($(this).attr("data-value")==0){				
				$this=this;				
				$.post(CI.base_url+'/admin/catalog/publish/'+studyid+'/1?ajax=1', {submit:"submit"}, function( data ) {
					$($this).attr("data-value",1);
					$($this).html("<?php echo t('published');?>");
					$($this).removeClass("btn-warning");
					$($this).addClass("btn-success");
				})
				.fail(function(xhr, status, error) {
					alert(xhr.responseText);
				});
			}
			else{
				$this=this;	
				$.post(CI.base_url+'/admin/catalog/publish/'+studyid+'/0?ajax=1', {submit:"submit"}, function( data ) {
					$($this).html("<?php echo t('draft');?>");
					$($this).attr("data-value",0);
					$($this).removeClass("btn-success");
					$($this).addClass("btn-warning");
				})
				.fail(function(xhr, status, error) {
					console.log(xhr,status,error);
				});
			}
		});


		//mark study as featured
		$(document.body).on("click","#survey .feature_study", function(){
			var studyid=$(this).attr("data-sid");
			var repoid=$(this).attr("data-repositoryid");
			var status=0;

			if ($(this).is(":checked")) {
				status=1;
			}

			$.post(CI.base_url+'/admin/catalog/set_featured_study/'+repoid+'/'+studyid+'/'+status+'?ajax=1',{submit:"submit"});
		});




		bind_behaviours();
});

//show/hide remote data access text box
function sh_remote_da_link()
{
	if ($("#formid").val()==5)
	{
		$(".link-da").show();
	}
	else
	{
		$(".link-da").hide();
	}
}

function clear_all_toggle()
{
	$("#survey .active").removeClass("active");
	$("#survey .box-body").addClass("collapse");
}

function toggle_sidebar(e){
	$(e).parent().toggleClass("iscollapsed");
	$(e).parent().find(".box-body").toggleClass("collapse");
}

function toggle_box(e){
		//clear_all_toggle();
		$(e).toggleClass("collapse");
		$(e).parent().find(".box-body").toggleClass("collapse");
		$(e).parent().parent("td").toggleClass("active");
}

function reset_box(e){
		var td=$(e).closest('td');
		td.removeClass("active");
		td.find(".collapse").removeClass("collapse");
		td.find(".box-body").addClass("collapse");
		console.log($(e));
}

function bind_behaviours() {
	bind_survey_collection_events();
}

function bind_survey_collection_events(){
	//click events for checkboxes
	$("#survey-collection-list .chk").click(function(e){
		update_survey_collection(this);
	});
}

function update_survey_collection(e) {
	var tid=$(e).val();
	var url=CI.base_url+'/admin/studycollections/detach/<?php echo $sid;?>/'+tid;

	if ($(e).is(":checked")) {
		url=CI.base_url+'/admin/studycollections/attach/<?php echo $sid;?>/'+tid;
	}

	$.ajax({
        type: "GET",
        url: url,
        cache: false,
		timeout:30000,
		success: function(data) {
        },
		error: function(XMLHttpRequest, textStatus, errorThrow) {
			alert(XMLHttpRequest.responseText);
        }
    });
}

	//related citations
	$(function() {
		//remove related citations
		$('#related-citations .remove').on('click',null, function() {
			$.get($(this).attr("href")+'/1');
			$(this).parent().parent().remove();
			return false;
		});

	});



	//related_studies_attach_studies selection dialog
	function dialog_select_related_studies()
	{
		var dialog_id='dialog-related-studies';
		var title="Select Studies";
		var survey_id=<?php echo $survey_id;?>;

		var tmp_id='sess-'+survey_id;//for saving dialog selection to cookies
		var url=CI.base_url+'/admin/dialog_select_studies/index/'+tmp_id;
		var get_selection_url=CI.base_url+"/admin/dialog_select_studies/get_list/"+tmp_id;
		var tab_id="#related-studies-tab";

		//already attached related studies
		var source_selected=get_selected_related_studies();

		//add attached surveys to session, needed when editing a citations with survey attached
		//var url_add=CI.base_url+'/admin/related_surveys/add/'+tmp_id+'/'+'<?php //echo implode(",",$selected_surveys_id_arr);?>/1';
		//$.get(url_add);	//update session
		if ($('#'+dialog_id).length==0){
			$("body").append('<div id="'+dialog_id+'" title="'+title+'"></div>');
		}

		var dialog=$( "#"+dialog_id ).dialog({
			height: 500,
			position:"center",
			width:750,
			modal: true,
			autoOpen: true,
			buttons: {
				"Cancel": function() {
					$( this ).dialog( "close" );
				},
				"Apply filter": function() {
					$.getJSON(get_selection_url, function( json ) {
					   var selected=json.selected;

					   //clear session selection
					   $.get(CI.base_url+'/admin/dialog_select_studies/clear_all/'+tmp_id);

					   //attach selected
					   var xhr=$.get(CI.base_url+'/admin/catalog/update_related_study/'+survey_id + '/'+selected + '/0');

					   //refresh the tab contents
					   $("#related-studies-tab").html("loading...");
					   xhr.done(function() {
					   		$("#related-studies-tab").load(CI.base_url+'/admin/catalog/get_related_studies/'+survey_id);
					   });
					 });

					$( this ).dialog( "close" );
				}
			}//end-buttons
		});//end-dialog

		//reset selected items each time dialog is loaded
		dialog.data("selected","");

		//load dialog content
		$('#'+dialog_id).load(url, function() {
			console.log("loaded");
		});

		//dialog pagination link clicks
		$(document.body).on("click","#related-surveys th a,#related-surveys .pagination a", function(){
			$("#dialog-related-studies").load( $(this).attr("href") );
			return false;
		});

		//dialog search button click
		 $(document.body).on("click","#dialog-related-studies .btn-search-submit", function(){
			data=$("#dialog-related-studies form").serialize();
			$("#dialog-related-studies").load( url+"?"+data );
			return false;
		});

		//dialog show selected only checkbox
		 $(document.body).on("click","#dialog-related-studies #show-only-selected", function(){
		 	if($(this).prop("checked")){
				data='show_selected_only=1';
			}
			else{data="";}
			$("#dialog-related-studies").load( url+"?"+data );
			return false;
		});

		//dialog attach/select study link
		$(document.body).on("click",".table-container a.attach", function(e){
			$.get($(this).attr("href"));
			$(this).html("<?php echo t('deselect'); ?>");
			$(this).removeClass("attach").addClass("remove");
			return false;
		});

		//dialog delest study link
		$(document.body).on("click","#related-surveys .table-container a.remove", function(){
			$.get($(this).attr("href"));
			$(this).html("<?php echo t('select'); ?>");
			$(this).removeClass("remove").addClass("attach");
			return false;
		});

	}//end-function

	//return array of selected items on the related study tab
	function get_selected_related_studies(){
		var items_selected=[];
		$("#related-studies-tab .table-related-studies .item").each(function(){
			items_selected.push($(this).attr("data-sid_2"));
		});
		return items_selected;
	}

	//relationship type change event
	$(document.body).on("change",".table-related-studies .rel-type", function(){
		var tr=$(this).closest("tr");
		var sid_1=tr.attr("data-sid_1");
		var sid_2=tr.attr("data-sid_2");
		var url=CI.base_url+'/admin/catalog/update_related_study/'+sid_1+'/'+sid_2+'/'+$(this).val();
		var url_remove_study=CI.base_url+'/admin/catalog/remove_related_study/'+sid_1+'/'+sid_2+'/'+$(this).val();
		tr.find(".remove-related-study").attr("href",url_remove_study);
		$.get(url);
		return false;
	});

	//remove related study link
	$(document.body).on("click",".table-related-studies .remove-related-study", function(){
		var survey_id=$(this).closest("tr").attr("data-sid_1");
		$.get($(this).attr("href")).done(function() {
			$("#related-studies-tab").load(CI.base_url+'/admin/catalog/get_related_studies/'+survey_id);
	   	});

		return false;
	});

	function set_data_access_display(el)
	{
		if ($("#formid").val() >=1 && $("#formid").val() <=3) {
			$("#formid").closest("form").addClass("microdata").removeClass("no-microdata");
		}
		else{
			$("#formid").closest("form").removeClass("microdata").addClass("no-microdata");
		}

	}

	//data access type change
	$(document.body).on("change","#formid", function(){
		set_data_access_display();
		return false;
	});


	$(function() {
		//set data access display on page load
		set_data_access_display();
	});

</script>

<style>
.filter-box{margin:5px;margin-right:20px;}
.filter-box li{font-size:11px;}
.filter-box a{text-decoration:none;color:black;display:block;padding:3px;padding-left:15px;background:url('images/bullet_green.png') left top no-repeat;}
.filter-box a:hover{background:black;color:white;}
.filter-field{
	border: 1px solid gainsboro;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
	color: #333;
	margin-bottom:10px;
}
.filter-title {
	font-size: 14px;
	text-transform: uppercase;
	padding: 5px;
	background: gainsboro;
}
span.active-repo{font-size:smaller;color:gray;}
span.link-change{font-size:10px;padding-left:5px;}
.unlink-study .linked{padding-left:20px;}
.width-80{width:80%;}
.collapse {display:none;}
.sh{float:right;display:block;}
.box-caption:hover{cursor:pointer;color:maroon;}


/*survey collections*/
#terms input {float:left;width:15px;margin-right:4px;}
#terms label {float:left;width:80%;font-size:smaller}
#terms {clear:both;}
#terms .term{clear:both;overflow:auto;margin-bottom:5px;}

.survey-tag{
	margin-right:5px;
	text-transform: uppercase;
}

/*editable rows*/
.collapsible .box-caption{
	line-height:150%;
}

.collapsible .box-caption .glyphicon {
	color:#337ab7;
}

td.active{background:gainsboro;}

/*fields*/
#survey .field label{font-weight:bold;display:block;}
#survey .field{margin-bottom:10px;}

/*hyperlinks*/
#survey a{font-size:small;}

#survey .actions{margin-right:20px;}

/*box*/
.box{
	border:1px solid gainsboro;
	margin-right:5px;
	line-height:150%;
	margin-bottom:10px;
	/*-webkit-border-radius: 3px;
	border-radius: 3px;*/
	clear:right;
}

.box-header{
	font-weight:normal;
	padding:5px;
	font-size:14px;
	background: #F1F1F1;
	background-image: -webkit-gradient(linear,left bottom,left top,from(#ECECEC),to(#F9F9F9));
	background-image: -webkit-linear-gradient(bottom,#ECECEC,#F9F9F9);
	background-image: -moz-linear-gradient(bottom,#ECECEC,#F9F9F9);
	background-image: -o-linear-gradient(bottom,#ECECEC,#F9F9F9);
	background-image: linear-gradient(to top,#ECECEC,#F9F9F9);
	border-bottom: 1px solid #DFDFDF;
	text-shadow: white 0 1px 0;
	-webkit-box-shadow: 0 1px 0 white;
	box-shadow: 0 1px 0 white;
	position:relative;
	cursor:pointer;
}

.box-header .sh{
	position:absolute;
	right:3px;
	top:5px;
	background: url('images/blue-remove.png') no-repeat left top;
	display:block;
	width:16px;
	height:16px;
	cursor:pointer;
}
.iscollapsed .sh{background: url('images/blue-add.png') no-repeat left top;}

.box-body{margin:5px;padding-bottom:10px;}
.box-body .input-flex{width:85%;}
.info-box{
  position: fixed;
  top: 20%;
  left:0px;
  width:100%;
/*  margin-top: -2.5em;*/
}
.info-box .error{background:red;margin-left:200px;margin-right:200px;color:white;display:none;}
.admin-notes-container .input-flex{width:85%;}
.reviewer-notes-container .input-flex{width:85%;}
.tags-container .input-flex{width:85%;}
.survey-other-ids .input-flex{width:85%;}
.remove{cursor:pointer;}
.tag{font-size:11px;}
.vscroll{overflow:auto;overflow-x:hidden;}
.survey-tabs .count{font-size:smaller;}

/*model dialog*/
.ui-widget-header{background:black;border:black;color:white;}
.ui-dialog .ui-dialog-content{overflow:hidden;padding:0px;background:white;}

/*related studies tab*/
.dialog-container .table-container {
	height: 246px;
	overflow: auto;
	font-size: 12px;
}

.dialog-container .pagination em{float:left;}
.dialog-container .pagination .page-nums{float:right;}

.dialog-container a.attach,
.dialog-container a.remove {
background: green;
padding: 3px;
color: white;
display: block;
-webkit-border-radius: 3px;
-moz-border-radius: 3px;
border-radius: 3px;
float:left;
width:60px;
text-align:center;
text-transform:capitalize
}
.dialog-container a.remove{background:red;}

.dialog-container a.attach:hover,
.dialog-container a.remove:hover {background:black;}


.ui-dialog .ui-dialog-titlebar-close {top:22%;}

.ui-widget-header {
background: white;
border: 0px;
color: black;
height: 56px;
}

/*dialog header*/
.ui-dialog .ui-dialog-titlebar {
	border-radius: 0px;
	border: 0px;
	text-align: left;
	margin-bottom: 10px;
	height: 35px;
	height: 1;
	padding-top: 31px;
	background:#F3F3F3
}

/*dialog footer*/
.ui-dialog .ui-dialog-buttonpane {
	font-size: 12px;
}

.grid-table .header{font-weight:bold;}
.sub-text{font-size:smaller;color:gray;}

.alert-warning{
border:2px solid #FF0000;
color:red;
}

.warnings{margin-top:5px;}
.red{color:red;}

.no-microdata .study-microdata{display:none;}
.no-microdata-assigned{color: red;
border: 1px solid red;
padding: 5px;
margin-bottom: 10px;
background: white;
}

.microdata-applies-to{font-weight:bold;}


/* toggle icon for collapse/expand */
.box-close, .box-open{color:gray;}
.box-header .box-close{display:none;}
.iscollapsed .box-open{display:none;}
.iscollapsed .box-close{display:block;}

/*custom badge color*/
.badge-light{
	background-color:#5bc0de
}

.study-edit-page .nav-tabs>li>a {
    /*background-color: #e8e8e8;*/
		border-radius:0px;
}

.study-edit-page .nav-tabs>li.active>a,
.study-edit-page .nav-tabs>li.active>a:focus,
.study-edit-page .nav-tabs>li.active>a:hover{
	font-weight:bold;
}

.nav-tabs>li {
    float: left;
    margin-bottom: -2px;
}

.label-repo-text{font-size:small;}
.study-attached-collections{
	max-height:300px;
	overflow:auto;
}
.alias{text-transform:uppercase;font-size:12px;color:gray;}

.featured_survey label{font-weight:normal;}

.label-tag{
	color:black;
	background:transparent;
	border:1px solid gainsboro;
	margin-right:2px;
	font-weight:normal;
}

.label-tag-error{
	background:red;
	color:white;
}

.label-tag-error a{
	color:white;
	font-weight:normal;
	font-size:10px;
}

.study-thumbnail{
	width:150px;
	height:150px;
	border:1px solid gainsboro;
}

.study-title{
	padding:0px;
	margin:0px;
}

.edit-page-header{
	margin-bottom:15px;
}
.modal-thumbnail-upload .modal-title{
	float:left;
}
</style>

<div class="container-fluid study-edit-page">


<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php if ($message!=""):?>
	<div style="margin-top:15px;" class="success alert alert-success alert-dismissible">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<?php echo $message;?>
	</div>
<?php endif;?>


<?php
	//current page url
	$page_url=site_url().'/'.$this->uri->uri_string();
?>

<div class="row">
<div class="col-md-12 edit-page-header">
		
	<div class="col-md-11">
		<h1 class="study-title"><?php echo $title; ?></h1>
	</div>
</div>


	<div id="survey" class="col-md-9">

		<div style="margin-bottom:15px;">
			<!-- Nav tabs -->
			<ul class="nav nav-tabs" role="tablist">
				<li role="presentation" <?php echo $selected_page=='' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid);?>" aria-controls="home" role="tab" ><?php echo t('tab_overview');?></a></li>
				<li role="presentation" <?php echo $selected_page=='metadata' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/metadata_editor/edit/'.$sid);?>" aria-controls="metadata-editor" role="tab" ><?php echo t('Metadata');?> </a></li>
				<li role="presentation" <?php echo $selected_page=='files' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/files');?>" aria-controls="profile" role="tab" ><?php echo t('tab_manage_files');?> <span class="badge badge-light"><?php echo count($files);?></span></a></li>
				<li role="presentation" <?php echo $selected_page=='resources' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/resources');?>" aria-controls="resources" role="tab" ><?php echo t('tab_resources');?> <span class="badge badge-light"><?php echo $resources['total'];?></span></a></li>
				<li role="presentation" <?php echo $selected_page=='citations' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/citations');?>" aria-controls="settings" role="tab" ><?php echo t('tab_citations');?> <span class="badge badge-light"><?php echo is_array($selected_citations) ? count($selected_citations) : '';?></span></a></li>

				<?php /* ?>
				<li role="presentation" <?php echo $selected_page=='data-files' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/data-files');?>" aria-controls="data-files" role="tab" ><?php echo t('tab_data_files');?> <span class="badge badge-light"><?php echo $data_files['total'];?></span></a></li>-->
				<?php */?>
				<li role="presentation" <?php echo $selected_page=='notes' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/notes');?>" aria-controls="settings" role="tab" ><?php echo t('tab_notes');?> <span class="badge badge-light"><?php echo is_array($study_notes) && count($study_notes) >0 ? count($study_notes) : '';?></span></a></li>
				<li role="presentation" <?php echo $selected_page=='related-data' ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/catalog/edit/'.$sid.'/related-data');?>" aria-controls="settings" role="tab" ><?php echo t('tab_related_data');?> <span class="badge badge-light"><?php echo is_array($related_studies) ? count($related_studies) : '';?></span></a></li>
				
			</ul>

		</div>


		<input name="tmp_id" type="hidden" id="tmp_id" value="<?php echo get_form_value('tmp_id',isset($tmp_id) ? $tmp_id: $this->uri->segment(4)); ?>"/>

		<div class="study-tab-container">
		<?php
			//load tab content
			switch($this->uri->segment(5)) {
				case 'resources':
					echo $resources['formatted'];
				break;
				case 'data-files':
				echo $data_files['formatted'];
			break;
				case 'citations':
					echo '<div id="related-citations" class="field related-citations">';
					$this->load->view('catalog/related_citations', array('related_citations'=>$selected_citations));
					echo '</div>';
				break;
				case 'related-data':
					echo '<div id="related-studies-tab" class="field related-studies-tab">';
					$this->load->view('catalog/related_studies_tab', array('related_studies'=>$related_studies));
					echo '</div>';
				break;
				case 'notes':
					$this->load->view('catalog/study_notes');
				break;
				case 'files':
					echo $files_formatted;
				break;
				case 'metadata':
					echo $metadata_editor;
				break;
				default:
					$this->load->view('catalog/edit_study_overview');
			}//end-switch
		?>
		</div>


	</div>
	<!--end survey info block-->

<div class="right-sidebar col-md-3">

<!-- Side Bars -->
<div class="box">
	<div class="box-header"><?php echo t('Status');?></div>
	<div class="box-body survey-publish">

		<div class="status" title="<?php echo t('click_to_publish_unpublish');?>">
			<?php if (!$published):?>
					<button type="button" class="btn btn-warning btn-block publish" data-value="0" data-sid="<?php echo $sid;?>"><?php echo t('draft');?></button>
			<?php else:?>
					<button type="button" class="btn btn-success btn-block publish" data-value="1"  data-sid="<?php echo $sid;?>"><?php echo t('published');?></button>
			<?php endif;?>
		</div>

		<div style="margin-top:10px;">
			<a
				class="btn btn-danger btn-block"
				href="<?php echo site_url();?>/admin/catalog/delete/<?php echo $sid;?>"
			>
			<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
				<?php echo t('delete_study');?>
			</a>
		</div>
	</div>
</div>

<?php if($warnings):?>
<div class="box iscollapsed">
  <div class="box-header">
		<span class="glyphicon glyphicon-alert red" aria-hidden="true"></span>
 		<?php echo t('study_warnings');?>
		<span class="label label-danger pull-right"><?php echo count($warnings);?></span>
	</div>
	<div class="box-body collapse">
	  <ul class="warnings">
	  <?php foreach($warnings as $warning):?>
	  <li><?php echo t($warning);?></li>
	  <?php endforeach;?>
	</ul>
	</div>
</div>
<?php endif;?>


<div class="box">
	<div class="box-header"><?php echo t('Thumbnail');?></div>
	<div class="box-body survey-thumbnail">

	<?php require_once 'application/views/catalog/thumbnail.php';?>	

		
	</div>
</div>

<div class="box" >
<div class="box-header">
	<span><?php echo t('Survey options');?></span>
		<span class="box-close pull-right glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
		<span class="box-open pull-right glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
</div>
<div class="box-body">
    <ul class="bull-list">
        <li><a target="_blank" href="<?php echo site_url();?>/catalog/<?php echo $sid;?>"><?php echo t('browse_metadata');?></a></li>
        <li><a href="<?php echo site_url();?>/admin/resources/import/<?php echo $sid;?>"><?php echo t('upload_rdf');?></a></li>
        <li><a href="<?php echo site_url();?>/admin/resources/fixlinks/<?php echo $sid;?>"><?php echo t('link_resources');?></a></li>
		<?php if($study_type=='survey'):?>
			<li><a href="<?php echo site_url();?>/admin/pdf_generator/setup/<?php echo $sid;?>"><?php echo t('generate_pdf');?></a></li>
			<li><a href="<?php echo site_url();?>/admin/catalog/replace_ddi/<?php echo $sid;?>"><?php echo t('replace_ddi');?></a></li>
			<li><a href="<?php echo site_url();?>/admin/catalog/ddi/<?php echo $sid;?>"><?php echo t('export_ddi');?></a></li>
			<li><a href="<?php echo site_url();?>/admin/catalog/refresh/<?php echo $sid;?>"><?php echo t('refresh_ddi');?></a></li>
		<?php endif;?>
        <li><a href="<?php echo site_url();?>/admin/catalog/transfer/<?php echo $sid;?>"><?php echo t('transfer_study_ownership');?></a></li>
        <li><a href="<?php echo site_url();?>/admin/catalog/export_rdf/<?php echo $sid;?>"><?php echo t('export_rdf');?></a></li>
        <li><a href="<?php echo site_url();?>/admin/catalog/delete/<?php echo $sid;?>"><?php echo t('delete_study');?></a></li>
    </ul>
</div>
</div>



</div>
<!-- end-right-bar -->

</div>
<!--end-row-->

</div>
<!-- end container -->
