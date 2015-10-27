<?php echo $header; ?>

<div class="container">
    <?php require( PAVO_THEME_DIR."/template/common/config_layout.tpl" );  ?>
    <?php require( PAVO_THEME_DIR."/template/common/breadcrumb.tpl" );  ?>
    <div class="row">


        <section id="sidebar-main" class="col-md-12"><div id="content"><?php echo $content_top; ?>
                <h1><?php echo $heading_title; ?></h1>
                <?php echo $text_message; ?>
                <div class="buttons">
                    <div class="pull-right"><a href="<?php echo $continue; ?>" class="button"><?php echo $button_continue; ?></a></div>
                </div>
                <?php echo $content_bottom; ?></div>
        </section>

</div>
<?php echo $footer; ?>