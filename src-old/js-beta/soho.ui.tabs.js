/**
 * \brief Gestionnaire d'onglet pour l'interface UI du projet SoHo.
 *
 * @package soho.ui.tabs
 */
WG.ui.tabs = {

	/**
	 * @var array<WG.ui.tabs.Tab> tabs La liste de tous les onglets.
	 */
	tabs: [],

	/**
	 * \brief Initialise le système d'onglet.
	 *
	 * Cette méthode va créer les éléments HTML néccessaires au système
	 * d'onglet, et mettre en place le comportement javascript. Il n'y a aucune
	 * vérification pour savoir si le système d'onglet a déjà été initialisé:
	 * si la méthode est appelée plusieurs fois, cela peut donner des résultats
	 * hasardeux.
	 *
	 * @return void
	 */
	init: function () {
	},

	/**
	 * \brief Supprime les onglets et désactive ce système.
	 *
	 * Cette méthode va supprimer les éléments HTML et les gestionnaires d'events
	 * associés au mécanisme d'onglets.
	 *
	 * @return void
	 */
	destroy: function () {
	},

	/**
	 * \brief Créé un nouvel onglet.
	 *
	 * @return int Le numéro d'index de l'onglet.
	 * @return null En cas d'erreur.
	 */
	create: function (title) {
	},

	/**
	 * \brief Renvoi un onglet par son numéro d'index.
	 *
	 * @param int index Le numéro d'index de l'onglet.
	 * @return WG.ui.tabs.Tab Si l'onglet a été trouvé.
	 * @return null Si aucun onglet ne ce trouve à cet index.
	 */
	get: function (index) {
	},

	/**
	 * \brief Retirer un onglet par son numéro d'index.
	 *
	 * @param int index Le numéro d'index de l'onglet à supprimer.
	 * @param int focus Le numéro d'index de l'onglet à mettre en focus après la suppression. [Optionnel]
	 * @return boolean Renvoi true si l'onglet a bien été supprimé, false sinon.
	 */
	remove: function (index, focus) {
	},

	/**
	 * \brief Modifie le titre d'un onglet.
	 *
	 * @param int index Numéro d'index de l'onglet à renommer.
	 * @param string title Nouveau titre à donner à l'onglet.
	 * @return boolean Renvoi true si l'onglet a bien été renommé, false sinon.
	 */
	retitle: function (index, title) {
	},

	/**
	 * \brief Renvoi le numéro d'index du premier onglet ayant le titre donné.
	 *
	 * La numérotation des onglets commence à la position 1.
	 *
	 * @param string title Le titre de l'onglet à rechercher.
	 * @return int Le numéro d'index du premier onglet en cas de succès.
	 * @return null Si aucun onglet ne porte ce titre.
	 */
	index: function (title) {
	},

	/**
	 * \brief Modifie la position d'un onglet.
	 *
	 * Si newIndex est inférieur à 1, l'onglet sera déplacé à la position 1 (c'est
	 * à dire le premier de la liste) et prendra l'index 1. Si newIndex est supérieur
	 * au nombre total d'onglet, l'onglet sera déplacé à la fin.
	 * 
	 *
	 * @param int oldIndex Numéro d'index de l'onglet à déplacer.
	 * @param int newindex Nouvelle position de l'onglet.
	 * @return boolean Renvoi true si l'onglet a bien été déplacé, false sinon.
	 */
	reindex: function (oldIndex, newIndex) {
	},

	/**
	 * 
	 */
	focus: function (index) {
	}

};

WG.ui.tabs.Tab = function (index, title) {
	this.index = index;
	this.title = title;
};

/**
 * \brief Modifie le niveau d'indication de l'onglet.
 *
 * @param WG.ui.tabs.TabState|null text
 */
WG.ui.tabs.Tab.prototype.setState = function (state) {
	if (typeof state == 'integer') {
		// Add classes depending on state, according to WG.ui.tabs.TabState
	}
	else {
		// Remove classes
	}
};

/**
 * \brief Modifie le badge text de cet onglet.
 *
 * @param string|null text
 */
WG.ui.tabs.Tab.prototype.setBadgeText = function (text) {
	if (typeof text == 'string') {
	}
	else {
	}
};

/**
 * \brief Affiche le contenu de cet onglet dans la frame principale.
 *
 * @return void
 */
WG.ui.tabs.Tab.prototype.focus = function () {
	WG.ui.tabs.focus(this.index);
};

WG.ui.tabs.TabState = {
	ERROR: 1,
	WARNING: 2,
	LOADING: 3
};