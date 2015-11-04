<div id="header">

    <header id="header-main">
        <div class="container">
            <div class="header-wrap">
                <div class="pull-left inner">
                    <div class="pull-left inner">
                        <?php if( $logoType=='logo-theme'){ ?>
                        <div  id="logo-theme" class="logo-store">
                            <a href="<?php echo $home; ?>">
                                <span><?php echo $name; ?></span>
                            </a>
                        </div>
                        <?php } elseif ($logo) { ?>
                        <div id="logo" class="logo-store">
                            <a href="<?php echo $home; ?>">
                                <img src="<?php echo $logo; ?>" title="<?php echo $name; ?>" alt="<?php echo $name; ?>" class="img-responsive" />
                            </a>
                        </div>
                        <?php } ?>
                    </div>

                </div>

            </div>
        </div>
    </header>
</div>





