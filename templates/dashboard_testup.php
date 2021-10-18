<form action="./" method="POST">
	<button type="submit" name="testup_action" class="button button-primary" value="deregister"><?=__('SHOW MAIN SITE','testup')?></button>
	<button type="submit" name="testup_action" class="button button-secondary" value="rebase"><?=__('REBASE TESTUP SITE','testup')?></button><br>
	<button type="submit" name="testup_action" class="button button-link" value="publish"><?=__('PUBLISH TESTUP SITE','testup')?></button>　
	<button type="submit" name="testup_action" class="button button-link button-link-delete" value="remove"><?=__('REMOVE TESTUP SITE','testup')?></button>
<?php wp_nonce_field('testup_action','_testup_nonce');?>
</form>