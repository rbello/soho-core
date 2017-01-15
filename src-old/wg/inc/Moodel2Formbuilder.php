<?php

class Moodel2Formbuilder {

	public static function createForm(MoodelStruct $model, $url, $params=array(), $lang='en') {
		// Create form
		$form = new Moodel2FormbuilderDefaultForm(
			$lang,
			$model->name().'_form',
			$url,
			'POST'
		);
		// Fetch fields
		foreach ($model->fields() as $name => $info) {
			// Exclude ignore fields
			if (in_array('ignore:'.$name, $params)) {
				continue;
			}
			// Exclude primary key
			if (in_array('primary_key', $info['mods'])) {
				continue;
			}
			// Set mandatory flag
			$mandatory = in_array('not_null', $info['mods']);
			// Foreign fields
			if ($info['foreign']) {
				$table = $info['foreign']['table'];
				if (array_key_exists('map:'.$name, $params)) {
					$titleField = $params['map:'.$name];
				}
				else if ($table->fieldExists('name')) {
					$titleField = 'name';
				}
				else if ($table->fieldExists('title')) {
					$titleField = 'name';
				}
				else {
					throw new Exception('Impossible de determiner le champ à utiliser pour afficher les'
						+ ' valeurs de la table externe '.$table->name().' (table '.$model->name().' champ '.$name.')');
				}
				$data = $table->get(
					array(), // where
					array($table->primary(), $titleField) // fields
				);
				$values = array();
				foreach ($data as $item) {
					$values["_".$item->id()] = $item->get($titleField);
				}
					$form->add(new SelectField('field_' . $name, ucfirst($name) . ':', $values, array(
						'mandatory' => $mandatory
					)));
				continue;
			}
			// Switch between field types
			switch ($info['type']) {
				case 'int' : case 'integer' :
					$f = new TextField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory
					));
					$f->setDataPattern('number-int-signed');
					$form->add($f);
					break;
				case 'text' :
					$form->add(new TextAreaField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory
					)));
					break;
				case 'float' : case 'double' :
					$f = new TextField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory
					));
					$f->setDataPattern('number-float-signed');
					$form->add($f);
					break;
				case 'string' :
					$form->add(new TextField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory
					)));
					break;
				case 'boolean' : case 'bool' :
					$form->add(new CheckBoxField('field_' . $name, ucfirst($name), array(
						'mandatory' => $mandatory
					)));
					break;
				case 'char' : case 'chr' :
					$form->add(new TextField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory,
						'maxLength' => intval($info['args'][0])
					)));
					break;
				case 'enum' :
					$form->add(new SelectField('field_' . $name, ucfirst($name) . ':', $info['args'], array(
						'mandatory' => $mandatory
					)));
					break;
				case 'datetime' :
					$form->add(new DateField('field_' . $name, ucfirst($name) . ':', array(
						'mandatory' => $mandatory
					)));
					break;
				case 'range' :
					$unit = isset($info['args'][2]) ? ' ' . $info['args'][2] : '';
					$form->add(new SliderField('field_' . $name, ucfirst($name) . ': %VALUE%' . $unit, array(
						'min' => intval($info['args'][0]),
						'max' => intval($info['args'][1]),
						'step' =>  isset($info['args'][3]) ? intval($info['args'][3]) : 1,
						'mandatory' => $mandatory
					)));
					break;
				default :
					throw new Exception("Unsupported field type '".$info['type']."' for field '$name'");
					break;
			}
		}
		return $form;
	}

}

class Moodel2FormbuilderDefaultForm extends AbstractForm {
	public function __construct($lang='en', $formName, $formActionURL, $formMethod='POST') {
		parent::__construct($lang, $formName, $formActionURL, $formMethod);
	}
}

?>