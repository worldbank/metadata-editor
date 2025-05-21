<footer>
    <!-- footer top section -->
    <?php //include_once 'footer_top.php';?>
    <!-- End footer top section -->

    <!-- footer bottom section -->
    <div class="full-row-footer-black-components">
        <div class="container">
            <div class="footer">
                <div class="row">

                    <!-- / footer logo -->
                    <div class="col-12 col-md-6">

                        <div class="text-center text-lg-left mt-2">
                        <?php /* logo ?>
                        <a href="<?php echo site_url();?>" class="g01v1-logo nada-logo">
                            <img style="width:50px;" title="<?php echo $this->config->item("website_title");?>" src="<?php echo base_url().$bootstrap_theme; ?>/images/logo-placeholder.png" class="img-responsive">                            
                        </a>
                        <?php */?>

                        <?php /* text only */ ?>
                        <a href="<?php echo site_url();?>" style="color:white;padding-top:20px;" >
                            Metadata Editor
                        </a>                        
                        <?php /* */?>
                        </div>

                    </div>
                    <!-- / footer logo -->

                    <!-- Copyrights-->
                    <div class="col-12 col-md-6 footer-bottom-right">
                        <div class="">
                            <div class="footer-copy-right">

                                <p class="text-center text-lg-right">

                                    <small>
                                        ©
                                        <script language="JavaScript">
                                            var time = new Date();
                                            var year = time.getYear();
                                            if (year < 2000)
                                                year = year + 1900 + ", ";
                                            document.write(year); // End -->
                                        </script> Metadata editor, All Rights Reserved.
                                    </small>

                                </p>

                            </div>

                        </div>
                    </div>
                    <!-- / Copyrights  -->

                </div>
            </div>
        </div>
    </div>
</footer>