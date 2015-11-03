<?php 
if (isset($_GET['route']) && $_GET['route'] == 'module/cart') $_GET['ajax'] = true;
global $_config;
$opencart_version = (int)str_replace('.','',VERSION);





function nico_get_config(){
	return 0;
}

$checkout_hide_fax = (int)nico_get_config('checkout_hide_fax');
$checkout_hide_tax_id = (int)nico_get_config('checkout_hide_tax_id');
$checkout_hide_company_id = (int)nico_get_config('checkout_hide_company_id');
$checkout_hide_company = (int)nico_get_config('checkout_hide_company');
$checkout_hide_address_2 = (int)nico_get_config('checkout_hide_address_2');
$checkout_hide_country = (int)nico_get_config('checkout_hide_country');
$checkout_hide_zone = (int)nico_get_config('checkout_hide_zone');
$checkout_hide_city = (int)nico_get_config('checkout_hide_city');
$checkout_hide_postcode = (int)nico_get_config('checkout_hide_postcode');
$checkout_hide_gift = (int)nico_get_config('checkout_hide_gift');
$checkout_hide_coupon = (int)nico_get_config('checkout_hide_coupon');
$checkout_hide_points = (int)nico_get_config('checkout_hide_points');

$checkout_payment_auto_cofirm = !(int)nico_get_config('checkout_payment_auto_cofirm');
echo $header;
?>
<style>
	.register_block .col-md-1, .register_block .col-md-2,.register_block .col-md-3,.register_block .col-md-4,
	.register_block .col-md-5,.register_block .col-md-6,.register_block .col-md-7,.register_block .col-md-8,.register_block .col-md-9
	,.register_block .col-md-10,.register_block .col-md-11,.register_block .col-md-12{
		float:left;
	}
	.text-danger{
		font-size:11px;
	}

</style>
<div class="container nicocheckout">
    <?php require( PAVO_THEME_DIR."/template/common/breadcrumb.tpl" );  ?>

		
		<div class="error"></div>


        <!-- if customer  no login, try login-->
		<?php if (!isset($customer_id)) {?>
		<div class="row">
			<div class="col-md-12">

			    <!--<div class="pull-left check-title"><h2 ><?php echo $heading_title; ?></h2></div>-->
			    <a href="#"  class="button check-login" data-toggle="modal" data-target="#mylogin"><?php echo $text_i_am_returning_customer; ?></a>

		<!-- Modal -->
		<div class="modal fade" id="mylogin" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
		<div class="modal-content">
		<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="myModalLabel">Login</h4>
		</div>
		<div class="modal-body">




		<form class="form-inline" role="form">


		<label class="control-label" for="input-email"><?php echo $entry_email; ?></label>
		<input type="text" name="email" value="" placeholder="<?php echo str_replace(':','',$entry_email); ?>" id="input-email" class="form-control" />


		<label class="control-label" for="input-password"><?php echo $entry_password; ?></label>
		<input type="password" name="password" value="" placeholder="<?php echo str_replace(':','',$entry_password); ?>" id="input-password" class="form-control" />
		<a href="<?php echo $forgotten; ?>"><?php echo $text_forgotten; ?></a>
		


		</form>

		</div>
		<div class="modal-footer">

		<input type="button" class="btn btn-primary" data-loading-text="<?php if (isset($text_loading)) echo $text_loading;else echo 'loading ...' ?>" value="<?php echo $button_login; ?>"></input>
		</div>
		</div>
		</div>
		</div>




		    </div>
		</div>
		<?php }?>
		
		<div class="row box checkout_form">
		    <div class="col-md-4 register_block">
				<!--<div class="row">-->
                        <div class="panel panel-default">
                            <div class="panel-collapse " >
                                <div class="panel-body">
                                    <h3 class="title-step"><?php echo $text_shipping_address; ?></h3>
                                    <div class="row">
<!-- start -->
                                <?php if ($shipping_required) {?>
                                <div class="shipping-address form-group col-md-12" style="display:block" >
                                <!--customer address list  start-->
                                <?php if ($addresses) { ?>
                                    <?php if (isset($customer_id)) {?>
                                    <div class="radio">
                                    <label>
                                    <input type="radio" name="shipping_address" value="existing" checked="checked" onclick="jQuery('#shipping-new').hide()" />
                                    <?php echo $text_address_existing; ?></label>
                                    </div>
                                    <div id="shipping-existing">

                                    <select name="shipping_address_id" class="form-control">
                                    <?php foreach ($addresses as $address) { ?>
                                    <?php if (isset($shipping_address_id) && $address['address_id'] == $shipping_address_id) { ?>
                                    <option value="<?php echo $address['address_id']; ?>" selected="selected"><?php echo $address['firstname']; ?> <?php echo $address['lastname']; ?>, <?php echo $address['address_1']; ?>, <?php echo $address['city']; ?>, <?php echo $address['zone']; ?>, <?php echo $address['country']; ?></option>
                                    <?php } else { ?>
                                    <option value="<?php echo $address['address_id']; ?>"><?php echo $address['firstname']; ?> <?php echo $address['lastname']; ?>, <?php echo $address['address_1']; ?>, <?php echo $address['city']; ?>, <?php echo $address['zone']; ?>, <?php echo $address['country']; ?></option>
                                    <?php } ?>
                                    <?php } ?>
                                    </select>
                                </div>
                                <?php } ?>

                                <?php if (isset($customer_id)) {?>
                                <div class="radio">
                                <label>
                                <input type="radio" name="shipping_address" value="new" onclick="jQuery('#shipping-new').show();"/>
                                <?php echo $text_address_new; ?></label>
                                </div>
                                <?php }?>
                            <?php } ?>

                                    <!--customer address list  end-->

                            <div id="shipping-new" style="display: <?php echo (($addresses && isset($customer_id)) ? 'none' : 'block'); ?>;">
                                <?php if (!isset($customer_id)) {?>
                                <div class="form-group required col-md-12">
                                <!--<label class="control-label" for="input-shipping-email"><?php echo $entry_email; ?></label>-->
                                <input type="text" name="email" value="" placeholder="<?php echo str_replace(':','',$entry_email); ?>" id="input-email" class="form-control" />
                                </div>
                                <?php } ?>
                                <div class=" form-group col-md-12">
                                <div class="row">
                                <div class=" required col-md-6">
                                    <!--<label class="control-label" for="input-shipping-firstname"><?php echo $entry_firstname; ?></label>-->

                                        <input type="text" name="shipping_firstname" value="<?php if (isset($shipping_firstname)) echo $shipping_firstname;?>" placeholder="<?php echo str_replace(':','',$entry_firstname); ?>" id="input-shipping-firstname" class="form-control" />

                                </div>
                                <div class=" required col-md-6">
                                    <!--<label class="control-label" for="input-shipping-lastname"><?php echo $entry_lastname; ?></label>-->

                                        <input type="text" name="shipping_lastname" value="<?php if (isset($shipping_lastname)) echo $shipping_lastname;?>" placeholder="<?php echo str_replace(':','',$entry_lastname); ?>" id="input-shipping-lastname" class="form-control" />

                                </div>

                                    </div>
                                </div>

                                <!--
                                <div class="form-group col-md-12">
                                <label class="control-label" for="input-shipping-company"><?php echo $entry_company; ?></label>

                                <input type="text" name="shipping_company" value="" placeholder="<?php echo str_replace(':','',$entry_company); ?>" id="input-shipping-company" class="form-control" />

                                </div>
                                    -->

                                <div class="form-group required col-md-12">
                                    <!--<label class="control-label" for="input-shipping-address-1"><?php echo $entry_address_1; ?></label>-->

                                        <input type="text" name="shipping_address_1" value="<?php if (isset($shipping_address_1)) echo $shipping_address_1;?>" placeholder="<?php echo str_replace(':','',$entry_address_1); ?>" id="input-shipping-address-1" class="form-control" />

                                </div>


                                <div class="form-group col-md-12">
                                <!--<label class="control-label" for="input-shipping-address-2"><?php echo $entry_address_2; ?></label>-->

                                <input type="text" name="shipping_address_2" value="<?php if (isset($shipping_address_2)) echo $shipping_address_2;?>" placeholder="<?php echo str_replace(':','',$entry_address_2); ?>" id="input-shipping-address-2" class="form-control" />

                                </div>


                                <div class="form-group required  col-md-12">
                                    <!--<label class="control-label" for="input-payment-telephone"><?php echo $entry_telephone; ?></label>-->
                                    <input type="text" name="shipping_telephone" value="<?php if (isset($shipping_telephone)) echo $shipping_telephone;?>" placeholder="<?php echo str_replace(':','',$entry_telephone); ?>" id="input-payment-telephone" class="form-control" />
                                </div>

                                <div class=" form-group col-md-12">
                                    <div class="row">
                                <div class="required col-md-6">
                                <!--<label class="control-label" for="input-shipping-city"><?php echo $entry_city; ?></label>-->

                                <input type="text" name="shipping_city" value="<?php if (isset($shipping_city)) echo $shipping_city;?>" placeholder="<?php echo str_replace(':','',$entry_city); ?>" id="input-shipping-city" class="form-control" />

                                </div>



                                <div class="required col-md-6">
                                <!--<label class="control-label" for="input-shipping-postcode"><?php echo $entry_postcode; ?></label>-->

                                <input type="text" name="shipping_postcode" value="<?php if (isset($shipping_postcode)) echo $shipping_postcode;?>" placeholder="<?php echo str_replace(':','',$entry_postcode); ?>" id="input-shipping-postcode" class="form-control" />

                                </div>

                                        </div></div>

                                <div class=" form-group col-md-12">
                                    <div class="row">
                                <div class=" required col-md-6">
                                <!--<label class="control-label" for="input-shipping-country"><?php echo $entry_country; ?></label>-->
                                <select name="shipping_country_id" id="input-shipping-country" class="form-control">
                                <option value=""><?php echo $text_select; ?></option>
                                <?php foreach ($countries as $country) { ?>
                                <?php if ($country['country_id'] == $shipping_country_id) { ?>
                                <option value="<?php echo $country['country_id']; ?>" selected="selected"><?php echo $country['name']; ?></option>
                                <?php } else { ?>
                                <option value="<?php echo $country['country_id']; ?>"><?php echo $country['name']; ?></option>
                                <?php } ?>
                                <?php } ?>
                                </select>
                            </div>



                            <div class=" required col-md-6">
                                <!--<label class="control-label" for="input-shipping-zone"><?php echo $entry_zone; ?></label>-->

                                    <select name="shipping_zone_id" id="input-shipping-zone" class="form-control">
                                    </select>

                            </div>
                            </div>
                                </div>


                                <?php if (!isset($customer_id)) {?>
                                <div class="form-group col-md-12">

                                    <input type="checkbox" name="register" checked="checked" onclick="if (this.checked == true) jQuery('.register-form').show(); else jQuery('.register-form').hide();">&nbsp;<lable class="control-label"><?php echo $text_register; ?></lable>
                                </div>



                                <div class="register-form form-group col-md-12" >
                                <div class="row">
                                <div class=" required col-md-6">
                                <!--<label class="control-label" for="input-payment-password"><?php echo $entry_password; ?></label>-->
                                <input type="password" name="password" value="" placeholder="<?php echo str_replace(':','',$entry_password); ?>" id="input-payment-password" class="form-control" />
                                </div>
                                <div class="required col-md-6">
                                <!--<label class="control-label" for="input-payment-confirm"><?php echo $entry_confirm; ?></label>-->
                                <input type="password" name="confirm" value="" placeholder="<?php echo str_replace(':','',$entry_confirm); ?>" id="input-payment-confirm" class="form-control" />
                                </div>
                                </div>
                                </div>
                                <?php } ?>



                        </div>
                    </div>

                    <?php }?>

<!-- end  -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

			<div class="col-md-4">
				<div class="panel panel-default">
					<!--
					<div class="panel-heading">
						<h4 class="panel-title"></h4>
					</div>
					-->
					<div class="panel-collapse open" >
						<div class="panel-body">

							<h3 class="title-step"><?php echo $text_shipping_method; ?></h3>
							<div class="shipping-method">
								<?php if ($error_warning) { ?>
								<div class="alert alert-warning"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?></div>
								<?php } ?>
								<?php if ($shipping_methods) { ?>

								<?php foreach ($shipping_methods as $shipping_method) { ?>
								<p><strong><?php echo $shipping_method['title']; ?></strong></p>
								<?php if (!$shipping_method['error']) { ?>
								<?php foreach ($shipping_method['quote'] as $quote) { ?>
								<div class="radio">
									<label>
										<?php if ($quote['code'] == $code || !$code) { ?>
										<?php $code = $quote['code']; ?>
										<input type="radio" name="shipping_method" value="<?php echo $quote['code']; ?>"  title="<?php echo $quote['title']; ?>" checked="checked" />
										<?php } else { ?>
										<input type="radio" name="shipping_method" value="<?php echo $quote['code']; ?>"  title="<?php echo $quote['title']; ?>" />
										<?php } ?>
										<?php echo $quote['title']; ?> - <?php echo $quote['text']; ?></label>
								</div>
								<?php } ?>
								<?php } else { ?>
								<div class="alert alert-danger"><?php echo $shipping_method['error']; ?></div>
								<?php } ?>
								<?php } ?>
								<?php } ?>
							</div>

							<h3 class="title-step"><?php echo $text_payment_method; ?></h3>
							<div class="payment-method">
								<?php if ($error_warning) { ?>
								<div class="alert alert-warning"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?></div>
								<?php } ?>
								<?php if ($payment_methods) { ?>
								<p><?php echo $text_payment_method; ?></p>
								<?php foreach ($payment_methods as $payment_method) { ?>
								<div class="radio">
									<label>
										<?php if ($payment_method['code'] == $code || !$code) { ?>
										<?php $code = $payment_method['code']; ?>
										<input type="radio" name="payment_method" value="<?php echo $payment_method['code']; ?>" title="<?php echo $payment_method['title']; ?>" checked="checked" />
										<?php } else { ?>
										<input type="radio" name="payment_method" value="<?php echo $payment_method['code']; ?>" title="<?php echo $payment_method['title']; ?>" />
										<?php } ?>
										<?php echo $payment_method['title']; ?></label>
								</div>
								<?php } ?>
								<?php } ?>
							</div>


						</div>
					</div>
				</div>
			</div>


		    <div class="col-md-4">
				<div class="panel panel-default">

					<div class="panel-collapse open" >
						<div class="panel-body">

			
				<div class="your_order">
		    		<h3 class="title-step"><?php echo $text_review_your_order; ?></h3>
					    <table id="cart_table" class="table table-responsive">

					      <tbody>

						  <tr>
							  <td class="text-left"><?php echo $column_name; ?></td>
							  <!--<th class="text-left hidden-xs"><?php echo $column_model; ?></th>-->
							  <td class="text-right hidden-xs"><?php echo $column_quantity; ?></td>
							  <!--<th class="text-right hidden-xs"><?php echo $column_price; ?></th>-->
							  <td class="text-right"><?php echo $column_total; ?></td>
						  </tr>


						<?php foreach ($products as $product) { ?>
						<tr>
						  <td class="text-left"><?php echo $product['name']; ?>
						    <?php foreach ($product['option'] as $option) { ?>
						    <br />
						    &nbsp;<small> - <?php echo $option['name']; ?>: <?php if (isset($option['option_value']) && !empty($option['option_value'])) echo $option['option_value'];else if (isset($option['value'])) echo $option['value']; ?></small>
						    <?php } ?></td>
						  <!--<td class="text-left hidden-xs"><?php echo $product['model']; ?></td>-->
						  <td class="text-right hidden-xs"><?php echo $product['quantity']; ?></td>
						  <!--<td class="text-right hidden-xs"><?php echo $product['price']; ?></td>-->
						  <td class="text-right"><?php echo $product['total']; ?></td>
						</tr>
						<?php } ?>
						<?php foreach ($vouchers as $voucher) { ?>
						<tr>
						  <td class="text-left"><?php echo $voucher['description']; ?></td>
						  <td class="text-left hidden-xs"></td>
						  <td class="text-right hidden-xs">1</td>
						  <td class="text-right hidden-xs"><?php echo $voucher['amount']; ?></td>
						  <td class="text-right"><?php echo $voucher['amount']; ?></td>
						</tr>
						<?php } ?>
					      </tbody>
					      <tfoot>
						<?php foreach ($totals as $total) { ?>
						<tr>
						  <td colspan="2" class="text-right">
							  <?php if($total['code'] == 'total') { ?>
							  <strong><?php echo $total['title']; ?>:</strong>
							  <?php } else { ?>
							  <?php echo $total['title']; ?>:
							  <?php } ?>
						  </td>
						  <td class="text-right">
							  <?php  if($total['code'] == 'total') { ?>
							  	<strong><?php echo $total['text']; ?></strong>
							  <?php } else { ?>
							  	<?php echo $total['text']; ?>
							  <?php } ?></td>
						</tr>
						<?php } ?>
					      </tfoot>
					    </table>

					<div class="payment clearfix">
					    <?php if ($payment) echo $payment; else {?>
					    <input type="button" class="btn btn-primary place-order col-md-12" data-loading-text="<?php if (isset($text_loading)) echo $text_loading;else echo 'loading ...' ?>" id="place-order" value="<?php echo $text_place_order_now;?>">
					    <?php }?></div>
					</div>
			    </div>
			</div>
						</div>
						</div>
						</div>
		</div>
	</div>		
      <?php echo $content_bottom; ?></div>
    <?php echo $column_right; ?></div>
</div>



<script type="text/javascript"><!--
var error = true;

// Login
$(document).delegate('#button-login', 'click', function() {
    $.ajax({
        url: 'index.php?route=checkout/nicocheckout/login_validate',
        type: 'post',
        data: $('.login-form :input'),
        dataType: 'json',
        beforeSend: function() {
        	$('#button-login').button('loading');
		},  
        complete: function() {
            $('#button-login').button('reset');
        },              
        success: function(json) {
            $('.alert, .text-danger').remove();
            
            if (json['redirect']) {
                location = json['redirect'];
            } else if (json['error']) {
                $('.login-form .message').prepend('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error']['warning'] + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
           }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    }); 
});

// Register
$(document).delegate('#place-order', 'click', function() 
{
	
	var data = $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked, .checkout_form textarea, .checkout_form select').serialize();
	data += '&_shipping_method='+ jQuery('.checkout_form input[name=\'shipping_method\']:checked').prop('title') + '&_payment_method=' + jQuery('.checkout_form input[name=\'payment_method\']:checked').prop('title');
	
    $.ajax({
        url: 'index.php?route=checkout/nicocheckout/validate',
        type: 'post',
        data: data,
        dataType: 'json',
        beforeSend: function() {
			$('#place-order').button('loading');
		},  
        complete: function() {
            $('#place-order').button('reset');
        },          
        success: function(json) {
            $('.alert, .text-danger').remove();
                        
            if (json['redirect']) {
                location = json['redirect'];                
            } else if (json['error']) {
				error = true;
                if (json['error']['warning']) {
                    $('.error').prepend('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error']['warning'] + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
                }
                
		for (i in json['error']) {
                    $('[name="' + i + '"], [name="' + i + '_id"]').after('<div class="text-danger">' + json['error'][i] + '</div>');
		}
            } else 
	    {
			error = false;
			jQuery('[name=\'payment_method\']:checked').click();
            }    
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    }); 
});

$('select[name=\'country_id\'], select[name=\'shipping_country_id\']').on('change', function() {
	_this = this;
	$.ajax({
        url: 'index.php?route=checkout/nicocheckout/country&country_id=' + this.value,
        dataType: 'json',
        beforeSend: function() {
			$(_this).before(' <i class="fa fa-spinner fa-spin"></i>');
        },
        complete: function() {
			$('.fa-spinner').remove();
        },          
        success: function(json) {
			if (json['postcode_required'] == '1') {
				$('input[name=\'postcode\']').parent().parent().addClass('required');
			} else {
				$('input[name=\'postcode\']').parent().parent().removeClass('required');
			}
			            
            html = '<option value=""><?php echo $text_select; ?></option>';
            
            if (json['zone'] && json['zone'] != '') {
                for (i = 0; i < json['zone'].length; i++) {
                    html += '<option value="' + json['zone'][i]['zone_id'] + '"';
                    
                    if (json['zone'][i]['zone_id'] == '<?php echo $zone_id; ?>') {
                        html += ' selected="selected"';
                    }
    
                    html += '>' + json['zone'][i]['name'] + '</option>';
                }
            } else {
                html += '<option value="0" selected="selected"><?php echo $text_none; ?></option>';
            }
            
            if (_this.name == 'shipping_country_id') 
            $('select[name=\'shipping_zone_id\']').html(html).val("");
            else $('select[name=\'zone_id\']').html(html).val("");
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    });
});

$('select[name=\'country_id\'], select[name=\'zone_id\'], select[name=\'shipping_country_id\'], select[name=\'shipping_zone_id\'], input[type=\'radio\'][name=\'payment_address\'], input[type=\'radio\'][name=\'shipping_address\']').on('change', function() 
{
	if (this.name == 'contry_id') jQuery("select[name=\'zone_id\']").val("");
	if (this.name == 'shipping_country_id') jQuery("select[name=\'shipping_zone_id\']").val("");
	
    jQuery(".shipping-method").load('index.php?route=checkout/nicocheckout/shipping_method', $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked,input[name=\'shipping_method\']:first, .checkout_form textarea, .checkout_form select'), function() 
    {
		if (jQuery("input[name=\'shipping_method\']:first").length) 
		{
			jQuery("input[name=\'shipping_method\']:first").attr('checked', 'checked').prop('checked', true).click();
		} else
		{
			jQuery("#cart_table").load('index.php?route=checkout/nicocheckout/cart', $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked, .checkout_form textarea, .checkout_form select'));	
		}
    });

	jQuery(".payment-method").load('index.php?route=checkout/nicocheckout/payment_method', $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked,input[name=\'shipping_method\']:first, .checkout_form textarea, .checkout_form select'), function() 
	{
		jQuery("[name=\'payment_method\']").removeAttr("checked").prop('checked', false);
	});
});    


$(document).delegate('input[name=\'shipping_method\']', 'click', function() 
{
    jQuery("#cart_table").load('index.php?route=checkout/nicocheckout/cart', $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked, .checkout_form textarea, .checkout_form select'));
});  

$('body').delegate('[name=\'payment_method\']','click', function() 
{

	var data = $('.checkout_form input[type=\'text\'], .checkout_form input[type=\'date\'], .checkout_form input[type=\'datetime-local\'], .checkout_form input[type=\'time\'], .checkout_form input[type=\'password\'], .checkout_form input[type=\'hidden\'], .checkout_form input[type=\'checkbox\']:checked, .checkout_form input[type=\'radio\']:checked, .checkout_form textarea, .checkout_form select').serialize();
	data += '&_shipping_method='+ jQuery('.checkout_form input[name=\'shipping_method\']:checked').prop('title') + '&_payment_method=' + jQuery('.checkout_form input[name=\'payment_method\']:checked').prop('title');

	if (!error)
    $.ajax({
        url: 'index.php?route=checkout/nicocheckout/confirm',
        type: 'post',
        data: data,
        success: function(html) 
        {
			jQuery(".payment").html(html);
			<?php if ($checkout_payment_auto_cofirm) {?>
			jQuery("#button-confirm").click();
			<?php } ?>
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    }); 
});

$('select[name=\'country_id\']').trigger('change');
jQuery(document).ready(function()
{
	jQuery('input:radio[name=\'payment_method\']:first').attr('checked', true).prop('checked', true);
	<?php /*if ($opencart_version < 2000) {?>
	$('.colorbox').colorbox({
		width: 640,
		height: 480
	});
	<?php }*/?>
});
//--></script> 

<?php echo $footer;?>
