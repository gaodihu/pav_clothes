<html>
<head>
    <style>
        .center{width:300px;margin:0 auto; margin-top:10%;}
    </style>
</head>
<body>
You will be redirected to the PayPal website in a few seconds.<br/>

<a href="<?php echo $button_continue_action; ?>">Click here if you are not redirected within 10 seconds... </a>
<script>
    window.location.href = "<?php echo $button_continue_action; ?>" ;
</script>

</body>
</html>
