<?php echo $header; ?>
<style>
  .sidebar{
    margin-top: 0;
  }
</style>
<div class="container">
    <?php require( PAVO_THEME_DIR."/template/common/config_layout.tpl" );  ?>
  
  <?php if ($success) { ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $success; ?></div>
  <?php } ?>
  <?php if ($error_warning) { ?>
  <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?></div>
  <?php } ?>
  <div class="row">
  
   <section id="sidebar-main" class="col-md-12">
    <?php require( PAVO_THEME_DIR."/template/common/breadcrumb.tpl" );  ?>
    <div id="content"><?php echo $content_top; ?>
      <div class="row">
        <div class="col-sm-6">
          <div class="well">
            <h2><?php echo $text_new_customer; ?></h2>
            <p><strong><?php echo $text_register; ?></strong></p>
            <p><?php echo $text_register_account; ?></p>
            <a href="<?php echo $register; ?>" class="button"><?php echo $button_continue; ?></a>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="well">
            <h2><?php echo $text_returning_customer; ?></h2>
            <p><strong><?php echo $text_i_am_returning_customer; ?></strong></p>
            <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data">
              <div class="form-group">
                <label class="control-label" for="input-email"><?php echo $entry_email; ?></label>
                <input type="text" name="email" value="<?php echo $email; ?>" placeholder="<?php echo $entry_email; ?>" id="input-email" class="form-control" />
              </div>
              <div class="form-group">
                <label class="control-label" for="input-password"><?php echo $entry_password; ?></label>
                <input type="password" name="password" value="<?php echo $password; ?>" placeholder="<?php echo $entry_password; ?>" id="input-password" class="form-control" />
                <a href="<?php echo $forgotten; ?>"><?php echo $text_forgotten; ?></a></div>
              <input type="submit" value="<?php echo $button_login; ?>" class="button" />
              <?php if ($redirect) { ?>
              <input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />
              <?php } ?>
            </form>
          </div>
        </div>
      </div>
      <?php echo $content_bottom; ?></div>
   </section> 
</div>
</div>
<?php echo $footer; ?>