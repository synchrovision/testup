<?php
wp_enqueue_script('alpinejs','//unpkg.com/alpinejs');
$db_conf_keys=['DB_NAME','DB_HOST','DB_USER','DB_PASSWORD'];
$db_conf=[];
foreach($db_conf_keys as $key){
	$db_conf[$key]=empty($_REQUEST['DB_CONF'][$key])?(constant($key).($key==='DB_NAME'?'_testup':'')):esc_attr($_REQUEST['DB_CONF'][$key]);
}
?>
<form action="./" method="POST" x-data='{input_db_conf:<?=$_REQUEST['input_db_conf']==1?'true':'false'?>,DB_CONF:<?=json_encode($db_conf,0500)?>}'>
	<div class="tablenav">
		<label><input class="form-check-input" type="checkbox" name="input_db_conf" value="1" x-model="input_db_conf"><?=__('Define Data Base','testup')?></label>
	</div>
	<table class="wp-list-table widefat fixed striped table-view-list" x-show="input_db_conf">
		<tbody>
<?php 		foreach($db_conf as $key=>$value): ?>
			<tr>
				<th class="column-slug"><?=$key?></th>
				<td>
					<div class="input-text-wrap">
						<input type="text" name="DB_CONF[<?=$key?>]" x-model="DB_CONF.<?=$key?>"/>
					</div>
				</td>
			</tr>
<?php 		endforeach; ?>
		</tbody>
	</table>
	<div class="tablenav">
		<button type="submit" name="testup_action" class="button button-primary" value="create"><?=__('CREATE TESTUP SITE','testup')?></button>
	</div>
<?php wp_nonce_field('testup_action','_testup_nonce');?>
</form>