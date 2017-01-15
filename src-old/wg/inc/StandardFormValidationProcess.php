<?php

interface StandardFormValidationProcessor {

	/**
	 * Demander l'affichage du listing.
	 * Cette fonction doit envoyer ses données directement à la sortie standard.
	 */
	public function displayListing();

	/**
	 * @return array
	 */
	public function listingButtons();

	/**
	 * Demander la création du formulaire.
	 * Cette fonction ne doit pas envoyer ses données directement à la sortie
	 * standard : elle doit renvoyer le formulaire.
	 *
	 * @param string $action L'URL cible du formulaire.
	 * @param Moodel $model Le model, en cas d'edition.
	 * @return AbstractForm Le formulaire.
	 */
	public function createForm($action, Moodel $model=null);

	public function onCreate(Moodel $model, Form $form);
	public function onCreated(Moodel $model);

	public function onUpdate(Moodel $new, Moodel $old, Form $form);
	public function onUpdated(Moodel $new, Moodel $old);

	public function onDelete(Moodel $model);
	public function onDeleted(Moodel $model);

}

class StandardFormValidationProcess {

	protected $model;
	protected $proc;
	protected $before = '';
	protected $after = '';

	public function __construct(MoodelStruct $model, StandardFormValidationProcessor $proc) {
		$this->model = $model;
		$this->proc = $proc;
	}

	public function exec() {

		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';
		$action_id = isset($_REQUEST['action_id']) ? intval($_REQUEST['action_id']) : -1;

		// DELETE
		if ($action === 'delete') {
			// Security
			# TODO
			// Search item
			$tmp = $this->model->getbyid($action_id);
			if ($tmp === null) {
				$this->before .= '<div id="poptop" class="error">Item not found. <a id="close-poptop">[close]</a></div>';
				// Display listing
				$this->listing();
				return;
			}
			// Send event
			$this->proc->onDelete($tmp);
			// Logical deletion
			if ($this->model->fieldType('deleted') !== null) {
				// Execute
				if ($tmp->set('deleted', 'yes')->save()) {
					$this->before .= '<div id="poptop">Item deleted! <a id="close-poptop">[close]</a></div>';
					$this->proc->onDeleted($tmp);
				}
				// Error
				else {
					$this->before .= '<div id="poptop" class="error">Unable to delete this item. <a id="close-poptop">[close]</a></div>';
				}
			}
			// Physical deletion
			else {
				// Execute
				if ($tmp->delete()) {
					$this->before .= '<div id="poptop">Item deleted! <a id="close-poptop">[close]</a></div>';
					$this->proc->onDeleted($tmp);
				}
				// Error
				else {
					$this->before .= '<div id="poptop" class="error">Unable to delete this item. <a id="close-poptop">[close]</a></div>';
				}
			}
			// Display listing
			$this->listing();
		}

		// EDIT
		else if ($action == 'edit') {
			$item = $this->model->get(array(
				$this->model->primary() => $action_id,
				'deleted' => 'no'
			));
			if (sizeof($item) > 0) {
				$this->form($item[0]);
			}
			else {
				$this->before .= '<div id="poptop" class="error">Item not found. <a id="close-poptop">[close]</a></div>';
				$this->listing();
			}
		}

		// CREATE
		else if (@$_REQUEST['action'] == 'create') {
			$this->form(null);
		}

		// LIST
		else {
			$this->listing();
		}

	}

	protected function listing() {
		echo '<div class="rightcommands">';
		$page = $GLOBALS['_PAGE'];
		echo '<a class="button" href="index.php?view='.$this->model->name().'&action=create"><img src="'.$page->resources.$this->model->name().'_add.png" /> Create '.print_proper_name($this->model->name(), true).'</a>';
		if (is_array($this->proc->listingButtons())) {
			foreach ($this->proc->listingButtons() as $b) {
				echo $b;
			}
		}
		echo '</div>';
		echo '<div class="singlecol">';
		echo $this->before;
		$this->proc->displayListing();
		echo $this->after;
		echo '</div>';
	}

	protected function form(Moodel $model=null) {
		// Create form
		$form = $this->proc->createForm($model !== null ? 'edit' : 'create', $model);
		// Create an empty model instance
		$item = $this->model->new;
		// Update
		if ($model != null) {
			// Store values
			foreach ($this->model->fields() as $name => $info) {
				if ($form->fieldExists('field_' . $name)) {
					if ($model->get($name) instanceof Moodel) {
						$form->getFieldByName('field_' . $name)->setValue('_'.$model->get($name)->id());
					}
					else {
						$form->getFieldByName('field_' . $name)->setValue($model->get($name));
					}
				}
			}
			// Store ID
			$item->set($this->model->primary(), $model->id());
			$form->add(new HiddenField('action_id', $model->id()));
		}
		// Execute form
		$form->execute();
		// Check form
		if ($form->submitted && $form->valid) {
			// Store form values in the model
			foreach ($this->model->fields() as $name => $info) {
				if (!$form->fieldExists('field_'.$name)) {
					continue;
				}
				if ($info['foreign']) {
					$key = substr($form->getFieldByName('field_'.$name)->getKey(), 1);
					$item->set($name, $key);
				}
				else {
					$item->set($name, $form->getFieldByName('field_'.$name)->getValue());
				}
			}

			// Events
			if ($model !== null) {
				$this->proc->onUpdate($item, $model, $form);
			}
			else {
				$this->proc->onCreate($item, $form);
			}
			// Save model instance
			try {
				$item->save();
				// Re-events
				if ($model !== null) {
					$this->proc->onUpdated($item, $model);
				}
				else {
					$this->proc->onCreated($item);
				}
				// TODO Right HTTP return code
				/*
					200	OK	Requête traitée avec succès
					201	Created	Requête traitée avec succès avec création d’un document
					202	Accepted	Requête traitée mais sans garantie de résultat
					203	Non-Authoritative Information	Information retournée mais générée par une source non certifiée
					204	No Content	Requête traitée avec succès mais pas d’information à renvoyer
					205	Reset Content	Requête traitée avec succès, la page courante peut être effacée
					206	Partial Content	Une partie seulement de la requête a été transmise
					207	Multi-Status	WebDAV : Réponse multiple.
					210	Content Different	WebDAV : La copie de la ressource côté client diffère de celle du serveur (contenu ou propriétés).
				*/
			}
			catch (Exception $ex) {
				if (strpos($ex->getMessage(), "Duplicate entry") !== false) {
					$this->before .= '<div class="result resulterror">'.htmlspecialchars($ex->getMessage()).'</div>';
				}
				else {
					$this->before .= '<div class="result resulterror"><strong>Database error</strong>. See logs for more information.</div>';
					if (WG::vars('dev_mode') === true) {
						$this->before .= '<div class="result resulterror"><strong>'.get_class($ex).'</strong> : '.htmlspecialchars($ex->getMessage()).'</div>';
					}
				}
			}
			// Display listing
			$this->listing();
		}
		else {
			echo '<div class="rightcommands">';
			echo '<a class="button" href="index.php?view='.$this->model->name().'&action=list">';
			echo '<img src="workgroop/modules/core/public/resultset_previous.png" />&nbsp;Cancel</a>';
			echo '</div>';
			echo '<div class="singlecol">';
			echo '<h2>'.($model !== null ? 'Edit ' : 'Create ').print_proper_name($this->model->name(), true).'</h2>';
			echo $form->render(array(
				'width-labels' => 200,
				'width-fields' => 400,
				'display-button-submit' => true,
				'display-button-reset' => false,
				'display-button-cancel' => false
			));
			echo '</div>';
		}
	}

}

?>