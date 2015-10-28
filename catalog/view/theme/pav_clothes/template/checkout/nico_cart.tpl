<table id="cart_table" class="table table-bordered table-hover table-responsive">
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
  <td colspan="2" class="text-right  hidden-xs">
    <?php  if($total['code'] == 'total') { ?>
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
    <?php } ?>

    </td>
</tr>
<?php } ?>
  </tfoot>
</table>
