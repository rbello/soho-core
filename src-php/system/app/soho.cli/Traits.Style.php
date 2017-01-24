<?php

namespace PHPonCLI;

trait Styles {
	
	/**
	 * Set output decorator.
	 * 
	 * @param int $value
	 * @return void
	 */
	public function setDecorator($value) {
	
		// Switch decorator type
		switch ($value) {
	
			// AINSI decoration
			case self::DECORATION_AINSI :
			case 'AINSI' :
				$this->decorator = function ($str, $style) {
					switch ($style) {
						case 'bold'     : return "\033[1m$str\033[22m";
						case 'underline': return "\033[4m$str\033[24m";
						case 'red'      : return "\033[31m$str\033[39m";
						case 'green'    : return "\033[32m$str\033[39m";
						case 'blue'     : return "\033[34m$str\033[39m";
						case 'orange'   : return "\033[33m$str\033[39m";
						case 'ok'       : return "[\033[1m\033[32m$str\033[39m\033[22m]";
						case 'failure'  : return "[\033[1m\033[31m$str\033[39m\033[22m]";
						case 'warning'  : return "[\033[1m\033[33m$str\033[39m\033[22m]";
						default        : return $str;
					}
				};
				break;
	
			// HTML decoration
			case self::DECORATION_HTML :
			case 'HTML' :
				$this->decorator = function ($str, $style) {
					switch ($style) {
						case 'bold'     : return "<b>$str</b>";
						case 'underline': return "<u>$str</u>";
						case 'italic'   : return "<i>$str</i>";
						case 'red'      : return "<span style='color:red'>$str</span>";
						case 'green'    : return "<span style='color:green'>$str</span>";
						case 'blue'     : return "<span style='color:blue'>$str</span>";
						case 'orange'   : return "<span style='color:orange'>$str</span>";
						case 'ok'       : return "<b>[<span style='color:green'>$str</span>]</b>";
						case 'failure'  : return "<b>[<span style='color:red'>$str</span>]</b>";
						case 'warning'  : return "<b>[<span style='color:orange'>$str</span>]</b>";
						default        : return $str;
					}
				};
				break;
	
			// No decorator
			case 'NONE' :
			default :
				$this->decorator = null;
				break;
	
		}
	
	}
	
	/**
	 * Apply a style to the given string.
	 * 
	 * @param string $str
	 * @param string $style
	 * @return string
	 */
	protected function decorate($str, $style) {
		$decorator = $this->decorator;
		return $decorator($str, $style);
	}
	
	/**
	 * Bold a text.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function bold($str) {
		return $this->decorator ? $this->decorate($str, 'bold') : $str;
	}
	
	/**
	 * Underline a text.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function underline($str) {
		return $this->decorator ? $this->decorate($str, 'underline') : $str;
	}
	
	/**
	 * Color a text in red.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function red($str) {
		return $this->decorator ? $this->decorate($str, 'red') : $str;
	}
	
	/**
	 * Color a text in green.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function green($str) {
		return $this->decorator ? $this->decorate($str, 'green') : $str;
	}
	
	/**
	 * Color a text in blue.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function blue($str) {
		return $this->decorator ? $this->decorate($str, 'blue') : $str;
	}
	
	/**
	 * Color a text in orange.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function orange($str) {
		return $this->decorator ? $this->decorate($str, 'orange') : $str;
	}
	
	/**
	 * Highlight a success text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function ok($str = 'OK') {
		return $this->decorator ? $this->decorate($str, 'ok') : "[$str]";
	}
	
	/**
	 * Highlight a failure text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function failure($str = 'FAILURE') {
		return $this->decorator ? $this->decorate($str, 'failure') : "[$str]";
	}
	
	/**
	 * Highlight a warning text message.
	 * 
	 * @param string $str
	 * @return string
	 */
	public function warn($str = 'WARNING') {
		return $this->decorator ? $this->decorate($str, 'warning') : "[$str]";
	}
}