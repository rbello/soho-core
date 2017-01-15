<h1>Config</h1>

<table class="data receipt">
  <thead>
	<tr>
		<th>Namespace.Key</th>
		<th>Current value</th>
		<th>Override</th>
		<th>Edit</th>
	</tr>
  </thead>
  <tbody>
<?php

foreach (WG::vars_raw() as $key => $data) {

	echo '<tr>';
	
	// Clé
	echo '<td>' . $data['ns'] . ".<b>$key</b></td>";
	
	// Valeur
	if (isset($data['isPassword'])) {
		echo '<td>'.str_repeat('*', strlen($data['value'])).'</td>';
	}
	else if (is_bool($data['value'])) {
		echo '<td>'.($data['value'] ? 'ON' : 'OFF').'</td>';
	}
	else if (is_array($data['value'])) {
		echo '<td>[' . htmlspecialchars(@implode(', ', $data['value'])).']</td>';
	}
	else {
		echo '<td>'.htmlspecialchars("{$data['value']}").'</td>';
	}
	

	
	// Override
	echo '<td>';
	if (sizeof($data['src']) > 0) {
		array_shift($data['src']);
		echo implode(', ', $data['src']);
	}
	echo '</td>';
	
	// Edition
	echo '<td>';
	if ($data['overridable'] === true) {
		if (isset($data['isPassword'])) {
			echo '<input type="password" />';
		}
		else if (isset($data['type'])) {
			switch ($data['type']) {
				case 'url' :
					echo '<input type="url" />';
					break;
			}
		}
		else if (is_bool($data['value'])) {
			echo '<input type="checkbox" id="field_'.$key.'" name="" class="handheld-checkbox-input" '.($data['value'] == true ? 'checked ' : '').'/>';
			echo '<label for="field_'.$key.'" class="handheld-checkbox">An hidden label</label>';
		}
		else if (is_string($data['value'])) {
			echo '<input type="text" value="'.htmlspecialchars($data['value']).'" />';
		}
		else if (is_int($data['value'])) {
			echo '<input type="number" min="0" max="10" step="2" value="'.($data['value'] === 0 ? '0' : $data['value']).'">';
		}
	}
	echo '</td>';
	
	echo '</tr>';
	
}



?>
  </tbody>
</table>