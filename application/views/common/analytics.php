<?php 
// Load analytics helper
$CI =& get_instance();
$CI->load->helper('analytics');

if (is_analytics_enabled()): 
?>
<!-- Analytics Tracking -->
<script src="<?php echo base_url('javascript/analytics.js'); ?>"></script>
<script>
  // Initialize Analytics with explicit configuration
  if (typeof Analytics !== 'undefined') {
    Analytics.init({
      siteUrl: '<?php echo site_url(); ?>',
      trackHashChanges: <?php echo analytics_track_hash_changes() ? 'true' : 'false'; ?>
    });
  }
</script>
<?php endif; ?>

