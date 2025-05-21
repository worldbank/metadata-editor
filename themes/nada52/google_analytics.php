<?php 
/*
      <!-- Global site tag (gtag.js) - Google Analytics -->
*/
?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $google_ua_code;?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '<?php echo $google_ua_code;?>');

  <?php 
    /**
     * 
     *  Custom events + downloads tracking
     * 
     * 
     */
    ?>

  $(function() {
    $(document).ajaxSend(function(event, request, settings) {
        gtag('event', 'page_view', {
            page_path: settings.url
        })
    });

    //track file downloads
    $('.resources .download').on('click', function() {
        gtag('event', 'download', {
            'event_label': $(this).attr("title"),
            'event_category': $(this).attr("href"),
            'non_interaction': true
        });
    });

});
</script>