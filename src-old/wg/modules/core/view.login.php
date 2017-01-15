<?php

if (WG::user() != null) {
	throw new WGSecurityException('Allready logged in');
}

// strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false;

?><div id="vkb">
	<div id="row0">
		<input name="accent" type="button" value="`" />
		<input name="1" type="button" value="1" />
		<input name="2" type="button" value="2" />
		<input name="3" type="button" value="3" />
		<input name="4" type="button" value="4" />
		<input name="5" type="button" value="5" />
		<input name="6" type="button" value="6" />
		<input name="7" type="button" value="7" />
		<input name="8" type="button" value="8" />
		<input name="9" type="button" value="9" />
		<input name="0" type="button" value="0" />
		<input name=" - " type="button" value=" - " />
		<input name="=" type="button" value="=" />
		<input name="backspace" type="button" value="Backspace" />
	</div>
	<div id="row0_shift">
		<input name="tilde" type="button" value="~" />
		<input name="exc" type="button" value="!" />
		<input name="at" type="button" value="@" />
		<input name="hash" type="button" value="#" />
		<input name="dollar" type="button" value="$" />
		<input name="percent" type="button" value="%" />
		<input name="caret" type="button" value="^" />
		<input name="ampersand" type="button" value="&" />
		<input name="asterik" type="button" value="*" />
		<input name="openbracket" type="button" value="(" />
		<input name="closebracket" type="button" value=")" />
		<input name="underscore" type="button" value="_" />
		<input name="plus" type="button" value="+" />
		<input name="backspace" type="button" value="Backspace" />
	</div>
	<div id="row1">
		<input name="q" type="button" value="q" />
		<input name="w" type="button" value="w" />
		<input name="e" type="button" value="e" />
		<input name="r" type="button" value="r" />
		<input name="t" type="button" value="t" />
		<input name="y" type="button" value="y" />
		<input name="u" type="button" value="u" />
		<input name="i" type="button" value="i" />
		<input name="o" type="button" value="o" />
		<input name="p" type="button" value="p" />
		<input name="[" type="button" value="[" />
		<input name="]" type="button" value="]" />
		<input name="\" type="button" value="\" />
	</div>
	<div id="row1_shift">
		<input name="Q" type="button" value="Q" />
		<input name="W" type="button" value="W" />
		<input name="E" type="button" value="E" />
		<input name="R" type="button" value="R" />
		<input name="T" type="button" value="T" />
		<input name="Y" type="button" value="Y" />
		<input name="U" type="button" value="U" />
		<input name="I" type="button" value="I" />
		<input name="O" type="button" value="O" />
		<input name="P" type="button" value="P" />
		<input name="{" type="button" value="{" />
		<input name="}" type="button" value="}" />
		<input name="|" type="button" value="|" />
	</div>
	<div id="row2">
		<input name="a" type="button" value="a" />
		<input name="s" type="button" value="s" />
		<input name="d" type="button" value="d" />
		<input name="f" type="button" value="f" />
		<input name="g" type="button" value="g" />
		<input name="h" type="button" value="h" />
		<input name="j" type="button" value="j" />
		<input name="k" type="button" value="k" />
		<input name="l" type="button" value="l" />
		<input name=";" type="button" value=";" />
		<input name="'" type="button" value="'" /> 
	</div>
	<div id="row2_shift">
		<input name="a" type="button" value="A" />
		<input name="s" type="button" value="S" />
		<input name="d" type="button" value="D" />
		<input name="f" type="button" value="F" />
		<input name="g" type="button" value="G" />
		<input name="h" type="button" value="H" />
		<input name="j" type="button" value="J" />
		<input name="k" type="button" value="K" />
		<input name="l" type="button" value="L" />
		<input name=";" type="button" value=":" />
		<input name="’" type="button" value='"' />
	</div>
	<div id="row3">
		<input name="Shift" type="button" value="Shift" id="shift" />
		<input name="z" type="button" value="z" />
		<input name="x" type="button" value="x" />
		<input name="c" type="button" value="c" />
		<input name="v" type="button" value="v" />
		<input name="b" type="button" value="b" />
		<input name="n" type="button" value="n" />
		<input name="m" type="button" value="m" />
		<input name="," type="button" value="," />
		<input name="." type="button" value="." />
		<input name="/" type="button" value="/" />
	</div>
	<div id="row3_shift">
		<input name="Shift" type="button" value="Shift" id="shifton" />
		<input name="Z" type="button" value="Z" />
		<input name="X" type="button" value="X" />
		<input name="C" type="button" value="C" />
		<input name="V" type="button" value="V" />
		<input name="B" type="button" value="B" />
		<input name="N" type="button" value="N" />
		<input name="M" type="button" value="M" />
		<input name="lt" type="button" value="&lt;" />
		<input name="gt" type="button" value="&gt;" />
		<input name="?" type="button" value="?" />
	</div>
	<div id="spacebar">
		<input name="spacebar" type="button" value=" " />
	</div>
</div>
<div id="loginform"><?php echo WG::session()->loginform(); ?></div>