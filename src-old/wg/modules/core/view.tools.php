<h1>Tools</h1>

<form action="index.php?view=tools">
	<p>MD5 : <input type="text" name="md5" /><input type="submit" />
		<?php echo empty($_REQUEST['md5']) ? '' : '<var>' . md5($_REQUEST['md5']) . '</var>'; ?></p>
	<p>SHA-1 : <input type="text" name="sha1" /><input type="submit" />
		<?php echo empty($_REQUEST['sha1']) ? '' : '<var>' . sha1($_REQUEST['sha1']) . '</var>'; ?></p>
	<p>SHA-256 : <input type="text" name="sha256" /><input type="submit" />
		<?php echo empty($_REQUEST['sha256']) ? '' : '<var>' . hash('sha256', $_REQUEST['sha256']) . '</var>'; ?></p>
</form>