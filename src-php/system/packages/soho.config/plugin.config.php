<?php

namespace Soho\Config;

class ConfigPlugin {


	/**
	 * @param mixed[] $values
	 * @param string $namespace 
	 * @param string $provider
	 */
	private function set($values, $namespace, $provider) {
		
		foreach ($values as $key => $value) {

			// On s'assure que le format d'entrée soit valide (un tableau)
			if (!is_array($value) || !isset($value['value'])) {
				$value = array('value' => $value);
			}
			
			// Remplacement automatique pour les strings
			if (is_string($value['value'])) {
				$value['value'] = str_replace('{$base.path}', self::$wgbase, $value['value']);
			}
			
			// La variable existe déjà
			if (array_key_exists($key, $this->vars)) {
				// On vérifie que la variable est bien overridable
				
				if (
						// Soit il s'agit du même module
						in_array($provider, $this->vars[$key]['src']) ||
						// Soit la variable l'autorise 
						isset($this->vars[$key]['overridable']) && $this->vars[$key]['overridable'] === true
				) {
					// On ajoute un provider
					$this->vars[$key]['src'][] = $provider;
					// Et on modifie la valeur de la variable
					$this->vars[$key]['value'] = $value['value'];
					// On passe à la variable suivante
					continue;
				}
				// Sinon c'est une erreur de sécurité
				else {
					throw new WGSecurityException("Variable `$key` is not overridable by `$provider`, allready defined by `{$this->vars[$key]['src'][0]}`");
				}
			}
			
			// La variable est nouvelle, on va écrire les données meta
			else {
				// L'espace de nom
				$value['ns'] = $namespace;
				// Et le fournisseur
				$value['src'] = array($provider);
				// Le status de remplacement
				$value['overridable'] = (isset($value['overridable']) && $value['overridable'] === true);
				// Et on modifie la valeur en laissant continuer
			}
			
			// On enregistre la variable
			$this->vars[$key] = $value;
			
		}

	}

	/**
	 * @return mixed|null
	 */
	protected function get($key) {
		if (!array_key_exists($key, $this->vars)) {
			return null;
		}
		return $this->vars[$key]['value'];
	}

	/**
	 * @return 
	 */
	public static function vars($key=null) {
		
		// Getter
		if ($key !== null) {
			// Variable doesn't exists
			if (!array_key_exists($key, self::$instance->vars)) {
				return null;
			}
			// Return result
			return self::$instance->vars[$key]['value'];
		}
		
		// Getter global
		
		throw new WGException("Not implemented yet");
		
		
		/*if ($key !== null) {
			$var = self::$instance->get($key);
			// Array handler
			if (is_array($var)) {
				// Subkey
				if ($subkey !== null) {
					$var = $var[$subkey];
				}
				// Nested value
				else if (isset($var['value'])) {
					$var = $var['value'];
				}
			}
			// Tokens replacement
			if (is_string($var)) {
				// TODO Ajouter base.url et current.url
				$var = str_replace(
					'{$base.path}',
					self::$wgbase,
					$var
				);
			}
			return $var;
		}
		else {
			return self::$instance->vars;
		}*/
	}
	
	/**
	 * TODO Virer les mdp
	 */
	public static function vars_raw() {
		return self::$instance->vars;
	}
	
}