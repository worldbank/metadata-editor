    /**** survey_summary_citations.php *****/
    $(document).ready(function () {
        $(".citation-row").click(function(){
            window.location=$(this).attr("data-url");
            return false;
        });
    });

    /**** access_licensed/request_form.php ****/

    $(document).ready(function()
    {
        $(".access_type").click(function() {
            $(".by-collection").hide();
            $(this).closest(".collection-container").find(".by-collection").show();
            $(".collapsible :checkbox").attr("disabled",true);//disable all checkboxes
            $(this).closest(".collapsible").find(":checkbox").attr("disabled",false);//enable checkboxes only for the current/active box

        });

        $(".select-all").click(function() {
            $(this).closest("table").find(":checkbox").prop('checked',true);
        });

        $(".clear-all").click(function() {
            $(this).closest("table").find(":checkbox").prop('checked',false);
        });

        $("#chk_agree").click(function() {
            $("#submit").prop('disabled', !$("#chk_agree").prop("checked"))
        });

        //disable submit button
        $("#submit").prop('disabled','disabled');

    });

    /**** access_direct/request_form.php &&  access_pulic/request_form.php ****/

    function isagree(){
        $("#submit").attr('disabled', !$("#chk_agree").attr("checked"))
    }


