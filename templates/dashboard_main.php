<form action="./" method="POST">
	<button type="submit" name="testup_action" class="button button-primary" value="register"><?=__('SHOW TESTUP SITE','testup')?></button>
<?php wp_nonce_field('testup_action','_testup_nonce');?>
</form>