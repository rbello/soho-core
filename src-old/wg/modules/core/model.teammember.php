<?php

$modelTeamMember = new MoodelStruct(DatabaseConnectorManager::getDatabase('main'), 'TeamMember', array(

	'id' => 'int:auto_increment,primary_key',
	'login' => 'string:unique',
	'name' => 'string',
	'cms_name_map' => 'string',
	'email' => 'string:unique',
	'password' => 'string',
	'flags' => 'char[20]',
	'thumb' => 'string',
	'color' => 'char[7]',
	'last_connection' => 'datetime',
	'last_medium' => 'char[10]',
	'apikey' => 'char[48]'

));

$modelTeamMember->setFactoryClass('TeamMember');

class TeamMember extends Moodel {

	/**
	 * Renvoyer le nom du flag le plus important dans $flags.
	 * @param string $flags
	 * @return string
	 */
	public static function flagname($flags) {
		$name = '(none)';
		$lvl = 0;
		$data = WG::flags();
		for ($i = strlen($flags) - 1; $i >= 0; $i--) {
			$f = $flags{$i};
			if (!isset($data[$f])) continue;
			$f = $data[$f];
			if ($f['level'] > $lvl) {
				$lvl = $f['level'];
				$name = $f['name'];
			}
		}
		return $name;
	}

	/**
	 * Obtenir le niveau maximum correspondant à des flags.
	 * @param string $flags
	 * @return int
	 */
	public static function flaglevel($flags) {
		$lvl = -1;
		$data = WG::flags();
		for ($i = strlen($flags) - 1; $i >= 0; $i--) {
			$f = $flags{$i};
			if (!isset($data[$f])) continue;
			$lvl = max($lvl, $data[$f]['level']);
		}
		return $lvl;
	}
	
	/**
	 * Tester si l'utilisateur est dans un groupe.
	 * @param string $group Nom du groupe
	 * @return boolean
	 */
	public function hasGroup($group) {
		$result = ModelManager::get('UserGroup')->get(array(
			'group' => $group,
			'user' => $this->get('id')
		));
		return sizeof($result) > 0;
	}

	/**
	 * Renvoi TRUE si l'utilisateur a le flag $flag.
	 * 
	 * @param string $flag
	 * @return boolean
	 */
	public function hasFlag($flag) {
		//echo "[$this->login $flag IN flags=$this->flags = ".((strpos($this->flags, $flag) !== false) ? 'TRUE' : 'FALSE')."]";
		return strpos($this->flags, $flag) !== false;
	}
	
	/**
	 * Renvoi le niveau de l'utilisateur.
	 * @return int
	 */
	public function level() {
		return self::flaglevel($this->flags);
	}

	/**
	 * Renvoi le nom du niveau de l'utilisateur.
	 * @return string
	 */
	public function levelname() {
		return self::flagname($this->flags);
	}
	
	/**
	 * Indique si l'utilisateur est connecté en ce moment.
	 * @return boolean
	 */
	public function isOnline() {
		return sizeof($this->getSessions() > 0);
	}
	
	/**
	 * Renvoi une liste de toutes les sessions actives de cet utilisateur.
	 * @param boolean $used Indique si c'est les sessions où l'utilisateur est utilisé qui doivent
	 *   être renvoyée, ou bien si c'est les sessions que l'utilisateur possède réellement (par défaut). 
	 * @return WGCRT_Session[]
	 */
	public function getSessions($used=false) {
		return WGCRT_Session::getUserSessions($this, $used);
	}

	/**
	 * Renvoi un tableau contenant toutes les instances des users.
	 * @return Moodel<TeamMember>[]
	 */
	public static function all() {
		return ModelManager::get('TeamMember')->all();
	}

	/**
	 * @return Moodel<Widget>[]
	 */
	public function getWidgets() {
		return ModelManager::get('Widget')->get(array(	'user' => $this->get('id')));
	}
	
	/**
	 * @return string
	 */
	public function getAbsoluteUserFolder() {
		return WG::vars('files_folder') . '/home/' . $this->get('login') . '/';
	}
	
	/**
	 * @return string
	 */
	public function getUserFolder() {
		return '/home/' . $this->get('login') . '/';
	}
	
}

?>
