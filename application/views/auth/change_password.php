<div class="container">

  <h1><?php echo t('change_password'); ?></h1>

  <?php if (validation_errors()) : ?>
    <div class="nada-error error">
      <?php echo validation_errors(); ?>
    </div>
  <?php endif; ?>

  <?php $error = $this->session->flashdata('error'); ?>
  <?php echo ($error != "") ? '<div class="error">' . $error . '</div>' : ''; ?>

  <?php $message = $this->session->flashdata('message'); ?>
  <?php echo ($message != "") ? '<div class="success">' . $message . '</div>' : ''; ?>

  <div class="row">
    <div class="col-12 col-sm-6 col-md-6 col-lg-6">
      <form method="post" autocomplete="off" class="form">
        <input type="hidden" name="<?php echo $csrf['keys']['name']; ?>" value="<?php echo $csrf['name']; ?>" />
        <input type="hidden" name="<?php echo $csrf['keys']['value']; ?>" value="<?php echo $csrf['value']; ?>" />

        <p><?php echo t('old_password'); ?>:<br />
          <?php echo form_input($old_password); ?>
        </p>

        <p><?php echo t('new_password'); ?>:<br />
          <?php echo form_input($new_password); ?>
        </p>

        <p><?php echo t('confirm_new_password'); ?>:<br />
          <?php echo form_input($new_password_confirm); ?>
        </p>

        <?php echo form_input($user_id); ?>
        <p><button class="btn btn-primary wb-btn" type="submit"><?php echo t('change'); ?></button></p>
    </div>
  </div>
  <?php echo form_close(); ?>

</div><!-- /.container -->