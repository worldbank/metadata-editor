<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/*
 * Server assistant mount (<pslm-server-chat>). Chat runs on the FastAPI service through
 * api/assistant/*; see controllers/api/Assistant.php and README-assistant.md.
 * Renders nothing unless server_assistant_enabled is set in config/editor.php.
 */
$this->load->config('editor');
if (!$this->config->item('server_assistant_enabled', 'editor')) {
    return;
}
$base = rtrim(base_url(), '/') . '/';
$version = function ($path) { return @filemtime(FCPATH . $path) ?: time(); };
$sid = isset($sid) ? $sid : '';
$launcher_offset = '20px';
?>
<pslm-server-chat data-server-assistant
    data-api="<?php echo html_escape($base); ?>"
    data-record-id="<?php echo html_escape($sid); ?>"
    data-launcher-offset="<?php echo $launcher_offset; ?>"></pslm-server-chat>
<script src="<?php echo html_escape($base . 'vue-app/assets/assistant-form.js?v=' . $version('vue-app/assets/assistant-form.js')); ?>"></script>
<script src="<?php echo html_escape($base . 'vue-app/assets/server-assistant-fill.js?v=' . $version('vue-app/assets/server-assistant-fill.js')); ?>"></script>
<script src="<?php echo html_escape($base . 'vue-app/assets/server-assistant.js?v=' . $version('vue-app/assets/server-assistant.js')); ?>"></script>
