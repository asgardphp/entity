<?php
namespace Asgard\Entity;

/**
 * Entity.
 * @property integer $id
 * @author Michel Hognerud <michel@hognerud.com>
 */
abstract class Entity {
	/**
	 * Entity data.
	 * @var array
	 */
	public $data = [ #public for behaviors
		'properties'          => [],
		'translations'        => [],
	];
	/**
	 * changed attributes.
	 * @var array
	 */
	protected $changed = [];
	/**
	 * Parameters.
	 * @var array
	 */
	protected $parameters = [];
	/**
	 * changed i18n attributes.
	 * @var array
	 */
	protected $translationschanged = [];
	/**
	 * Default locale.
	 * @var string
	 */
	protected $locale;
	/**
	 * Entity definition.
	 * @var Definition
	 */
	protected $definition;

	/**
	 * Constructor.
	 * @param array  $attrs
	 * @param string $locale
	 * @param EntityManager $entityManager
	 * @param boolean $loadDefault
	 */
	public function __construct(array $attrs=null, $locale=null, Definition $definition=null, $loadDefault=true) {
		$this->definition = $definition;
		$this->setLocale($locale);
		if($loadDefault)
			$this->loadDefault();
		if(is_array($attrs))
			$this->set($attrs);
	}

	/**
	 * Return the default locale.
	 * @return string
	 */
	public function getLocale() {
		if($this->locale === null)
			$this->locale = $this->getDefinition()->getEntityManager()->getDefaultLocale();
		return $this->locale;
	}

	/**
	 * Set the entity definition.
	 * @param Definition $definition
	 */
	public function setDefinition($definition) {
		$this->definition = $definition;
		return $this;
	}

	/**
	 * Set the default locale.
	 * @param string $locale
	 */
	public function setLocale($locale) {
		$this->locale = $locale;
	}

	public function __sleep() {
		return ['data', 'locale', 'changed', 'translationschanged'];
	}

	/**
	 * __set magic method.
	 * @param string $name
	 * @param mixed  $value
	 */
	public function __set($name, $value) {
		$this->set($name, $value);
	}

	/**
	 * __get magic method.
	 * @param  string $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->get($name);
	}

	/**
	 * __isset magic method.
	 * @param  string  $name
	 * @return boolean
	 */
	public function __isset($name) {
		$name = strtolower($name);
		return isset($this->data['properties'][$name]);
	}

	/**
	 * __unset magic method.
	 * @param string $name
	 */
	public function __unset($name) {
		$name = strtolower($name);
		unset($this->data['properties'][$name]);
	}

	/**
	 * __callStatic magic method. For active-record-like entities only.
	 * @param  string $name
	 * @param  array  $arguments
	 * @return mixed
	 */
	public static function __callStatic($name, array $arguments) {
		return static::getStaticDefinition()->callStatic($name, $arguments);
	}

	/**
	 * __call magic method. For active-record-like entities only.
	 * @param  string $name
	 * @param  array  $arguments
	 * @return mixed
	 */
	public function __call($name, array $arguments) {
		return $this->getDefinition()->call($this, $name, $arguments);
	}

	/**
	 * Initialize the configuration. To be overwritten in the entity class.
	 * @param  Definition $Definition
	 */
	public static function definition(Definition $Definition) {}

	/**
	 * Return the definition.
	 * @return Definition
	 */
	public function getDefinition() {
		if(!$this->definition)
			$this->definition = static::getStaticDefinition();
		return $this->definition;
	}

	/**
	 * Return a static definition, if entity used like active-record.
	 * @return Definition
	 */
	public static function getStaticDefinition() {
		#only for entities without dependency injection, activerecord like, e.g. new Article or Article::find();
		return EntityManager::singleton()->get(get_called_class());
	}

	/**
	 * Load default data.
	 * @return Entity
	 */
	public function loadDefault() {
		foreach($this->getDefinition()->properties() as $name=>$property)
			$this->_set($name, $property->getDefault($this, $name), null, false);
		return $this;
	}

	/**
	 * Check if the entity has no id.
	 * @return boolean true if entity has no id
	 */
	public function isNew() {
		return $this->get('id') === null;
	}

	/**
	 * Check if the entity has an id.
	 * @return boolean true if entity has an id
	 */
	public function isOld() {
		return !$this->isNew();
	}

	/**
	 * Get a validator.
	 * @param  array $locales
	 * @return \Asgard\Validation\ValidatorInterface
	 */
	public function getValidator(array $locales=[]) {
		$validator = $this->getDefinition()->getEntityManager()->createValidator();
		return $this->prepareValidator($validator, $locales);
	}

	/**
	 * Prepare the validator.
	 * @param  \Asgard\Validation\ValidatorInterface $validator
	 * @param  array                                 $locales
	 * @return \Asgard\Validation\ValidatorInterface
	 */
	public function prepareValidator(\Asgard\Validation\ValidatorInterface $validator, array $locales=[]) {
		$this->getDefinition()->trigger('validation', [$this, $validator], function($chain, $entity, $validator) use($locales) {
			$messages = [];

			foreach($this->getDefinition()->properties() as $name=>$property) {
				if($locales && $property->get('i18n')) {
					foreach($locales as $locale) {
						if($property->get('many')) {
							if($this->get($name, null, false) instanceof ManyCollection) {
								foreach($this->get($name, null, false) as $k=>$v) {
									$propValidator = $validator->attribute($name.'.'.$locale.'.'.$k);
									$property->prepareValidator($propValidator);
									$propValidator->formatParameters(function(&$params) use($name) {
										$params['attribute'] = $name;
									});
									$propValidator->ruleMessages($property->getMessages());
								}
							}
						}
						else {
							$propValidator = $validator->attribute($name.'.'.$locale);
							$property->prepareValidator($propValidator);
							$propValidator->formatParameters(function(&$params) use($name) {
								$params['attribute'] = $name;
							});
							$propValidator->ruleMessages($property->getMessages());
						}
					}
				}
				else {
					if($property->get('many')) {
						if($this->get($name, null, false) instanceof ManyCollection) {
							foreach($this->get($name, null, false) as $k=>$v) {
								$propValidator = $validator->attribute($name.'.'.$k);
								$property->prepareValidator($propValidator);
								$propValidator->formatParameters(function(&$params) use($name) {
									$params['attribute'] = $name;
								});
								$propValidator->ruleMessages($property->getMessages());
							}
						}
					}
					else {
						$propValidator = $validator->attribute($name);
						$property->prepareValidator($propValidator);
						$propValidator->ruleMessages($property->getMessages());
					}
				}
			}

			$messages = array_merge($messages, $this->getDefinition()->messages());
			$validator->attributesMessages($messages);

			$validator->set('entity', $this);
		});

		return $validator;
	}

	/**
	 * Check if entity is valid.
	 * @param  array   $groups validation groups
	 * @return boolean
	 */
	public function valid($groups=[]) {
		$data = $this->toArrayRaw();
		$validator = $this->getValidator();
		return $this->getDefinition()->trigger('validation', [$this, $validator, $groups, &$data], function($chain, $entity, $validator, $groups, &$data) {
			return $validator->valid($data);
		});
	}

	/**
	 * Return entity errors.
	 * @param  array|string|null $groups validation groups
	 * @return \Asgard\Validation\Report
	 */
	public function errors($groups=[]) {
		$data = $this->toArrayRaw();
		$validator = $this->getValidator();
		$report = $this->getDefinition()->trigger('validation', [$this, $validator, $groups, &$data], function($chain, $entity, $validator, $groups, &$data) {
			$report = $validator->errors($data, $groups);
			$report->setSelf('Entity is not valid');
			return $report;
		});

		return $report;
	}

	/**
	 * Hard set data. No pre-processing.
	 * @param array|string $name
	 * @param mixed        $value
	 * @param string       $locale
	 * @param boolean      $change
	 */
	public function _set($name, $value=null, $locale=null, $change=true) {
		if(is_string($name))
			$name = strtolower($name);

		if(is_array($name)) {
			$locale = $value;
			$vars = $name;
			foreach($vars as $name=>$value)
				$this->_set($name, $value, $locale);
			return $this;
		}

		if($this->getDefinition()->hasProperty($name)) {
			if(!$locale)
				$locale = $this->getLocale();

			if($this->getDefinition()->property($name)->get('i18n') && $locale !== $this->getLocale()) {
				if($locale == 'all') {
					foreach($value as $locale => $v) {
						if(isset($this->data['translations'][$locale][$name]) && $v === $this->data['translations'][$locale][$name])
							continue;
						$this->data['translations'][$locale][$name] = $v;
						if($change) {
							if(!isset($this->translationschanged[$locale]))
								$this->translationschanged[$locale] = [];
							$this->translationschanged[$locale][$name] = true;
						}
					}
				}
				else {
					if(isset($this->data['translations'][$locale][$name]) && $value === $this->data['translations'][$locale][$name])
						return $this;
					$this->data['translations'][$locale][$name] = $value;
					if($change) {
						if(!isset($this->translationschanged[$locale]))
							$this->translationschanged[$locale] = [];
						$this->translationschanged[$locale][$name] = true;
					}
				}
			}
			else {
				if(isset($this->data['properties'][$name]) && $value === $this->data['properties'][$name])
					return $this;
				$this->data['properties'][$name] = $value;
				if($change) {
					if($this->getDefinition()->property($name)->get('i18n'))
						$this->translationschanged[$this->getLocale()][$name] = true;
					else
						$this->changed[$name] = true;
				}
			}
		}
		elseif($name !== 'translations' && $name !== 'properties')
			$this->data[$name] = $value;

		return $this;
	}

	/**
	 * Soft set data. With pre-processing.
	 * @param array|string $name
	 * @param mixed        $value
	 * @param string       $locale
	 * @param boolean      $hook
	 * @param boolean      $change
	 * @param boolean      $silentException
	 */
	public function set($name, $value=null, $locale=null, $hook=true, $change=true, $silentException=false) {
		if(is_string($name))
			$name = strtolower($name);

		#setting multiple properties at once
		if(is_array($name)) {
			$vars = $name;
			$locale = $value;
			foreach($vars as $name=>$value)
				$this->set($name, $value, $locale, $hook);
			return $this;
		}

		#setting a property multiple translations at once
		if(is_array($locale)) {
			foreach($locale as $one)
				$this->set($name, $value[$one], $one, $hook);
			return $this;
		}

		$this->getDefinition()->processPreSet($this, $name, $value, $locale, $hook, $silentException);

		if($this->getDefinition()->hasProperty($name)) {
			if(!$locale)
				$locale = $this->getLocale();

			if($this->getDefinition()->property($name)->get('i18n') && $locale !== $this->getLocale()) {
				if($locale == 'all') {
					foreach($value as $locale => $v) {
						if(isset($this->data['translations'][$locale][$name]) && $v === $this->data['translations'][$locale][$name])
							continue;
						$this->data['translations'][$locale][$name] = $v;
						if($change) {
							if(!isset($this->translationschanged[$locale]))
								$this->translationschanged[$locale] = [];
							$this->translationschanged[$locale][$name] = true;
						}
					}
				}
				else {
					if(isset($this->data['translations'][$locale][$name]) && $value === $this->data['translations'][$locale][$name])
						return $this;
					$this->data['translations'][$locale][$name] = $value;
					if($change) {
						if(!isset($this->translationschanged[$locale]))
							$this->translationschanged[$locale] = [];
						$this->translationschanged[$locale][$name] = true;
					}
				}
			}
			else {
				if(isset($this->data['properties'][$name]) && $value === $this->data['properties'][$name])
					return $this;
				$this->data['properties'][$name] = $value;
				if($change) {
					if($this->getDefinition()->property($name)->get('i18n'))
						$this->translationschanged[$this->getLocale()][$name] = true;
					else
						$this->changed[$name] = true;
				}
			}
		}
		elseif($name !== 'translations' && $name !== 'properties') {
			if(is_array($value)) {
				$coll = new ManyCollection($this, $name);
				$value = $coll->setAll($value);
			}
			$this->data[$name] = $value;
		}

		return $this;
	}

	/**
	 * Hard get data. With no pre-processing.
	 * @param  string       $name
	 * @param  string|array $locale
	 * @return mixed
	 */
	public function _get($name, $locale=null) {
		$name = strtolower($name);
		
		if(!$locale)
			$locale = $this->getLocale();

		if($this->getDefinition()->hasProperty($name)) {
			if($this->getDefinition()->property($name)->get('i18n') && $locale !== $this->getLocale()) {
				#multiple locales at once
				if(is_array($locale)) {
					$res = [];
					foreach($locale as $locale)
						$res[$locale] = $this->get($name, $locale);
					return $res;
				}
				elseif(isset($this->data['translations'][$locale][$name]))
					return $this->data['translations'][$locale][$name];
				else {
					$this->getDefinition()->trigger('getTranslations', [$this, $name, $locale]);
					if(!isset($this->data['translations'][$locale][$name]))
						return null;
					return $this->data['translations'][$locale][$name];
				}
			}
			elseif(isset($this->data['properties'][$name]))
				return $this->data['properties'][$name];
		}
		elseif(isset($this->data[$name]))
			return $this->data[$name];
	}

	/**
	 * Hard get data. With pre-processing.
	 * @param  string       $name
	 * @param  string|array $locale
	 * @param  boolean      $hook
	 * @return mixed
	 */
	public function get($name, $locale=null, $hook=true) {
		if(!$locale)
			$locale = $this->getLocale();

		if($hook && ($res = $this->getDefinition()->trigger('get', [$this, $name, $locale])) !== null)
			return $res;

		return $this->_get($name, $locale);
	}

	/**
	 * Return a serializer.
	 * @return Serializer
	 */
	protected function getSerializer() {
		return $this->getDefinition()->getEntityManager()->getSerializer();
	}

	/**
	 * Convert entity to a raw array.
	 * @param  integer $depth
	 * @return array
	 */
	public function toArrayRaw($depth=0) {
		return $this->getSerializer()->toArrayRaw($this, $depth);
	}

	/**
	 * Convert entity to a formatted array.
	 * @param  integer $depth
	 * @return array
	 */
	public function toArray($depth=0) {
		return $this->getSerializer()->toArray($this, $depth);
	}

	/**
	 * Convert entity to json.
	 * @param  integer $depth
	 * @return string
	 */
	public function toJSON($depth=0) {
		return $this->getSerializer()->toJSON($this, $depth);
	}

	/**
	 * Return entity in another language.
	 * @param  string $locale
	 * @return Entity
	 */
	public function translate($locale) {
		$localeEntity = clone $this;
		$localeEntity->setLocale($locale);
		if(isset($this->data['translations'][$locale]))
			$localeEntity->_set($this->data['translations'][$locale]);
		unset($localeEntity->data['translations'][$locale]);
		$localeEntity->data['translations'][$this->getLocale()] = $this->data['properties'];

		return $localeEntity;
	}

	/**
	 * Get entity locales.
	 * @return array
	 */
	public function getLocales() {
		return array_merge([$this->getLocale()], array_keys($this->data['translations']));
	}

	/**
	 * Convert entity to a raw array with translations.
	 * @param  array $locales
	 * @param  integer $depth
	 * @return array
	 */
	public function toArrayRawI18N(array $locales=[], $depth=0) {
		return $this->getSerializer()->toArrayRawI18N($this, $locales, $depth);
	}

	/**
	 * Convert entity to a formatted array with translations.
	 * @param  array $locales
	 * @param  integer $depth
	 * @return array
	 */
	public function toArrayI18N(array $locales=[], $depth=0) {
		return $this->getSerializer()->toArrayI18N($this, $locales, $depth);
	}

	/**
	 * Convert entity to JSON with translations.
	 * @param  array $locales
	 * @param  integer $depth
	 * @return string
	 */
	public function toJSONI18N(array $locales=[], $depth=0) {
		return $this->getSerializer()->toJSONI18N($this, $locales, $depth);
	}

	/**
	 * Check if entity and translations are valid.
	 * @param  string[] $locales
	 * @param  array    $groups validation groups
	 * @return boolean
	 */
	public function validI18N(array $locales=[], $groups=[]) {
		if(!$locales)
			$locales = $this->getLocales();
		$data = $this->toArrayRawI18N($locales);
		$validator = $this->getValidator($locales);
		return $validator->valid($data, $groups);
	}

	/**
	 * Return errors for entity and translations.
	 * @param  string[] $locales
	 * @param  array    $groups validation groups
	 * @return \Asgard\Validation\Report
	 */
	public function errorsI18N(array $locales=[], $groups=[]) {
		if(!$locales)
			$locales = $this->getLocales();
		$data = $this->toArrayRawI18N($locales);
		$validator = $this->getValidator($locales);

		return $validator->errors($data, $groups);
	}

	public function getchanged() {
		return array_merge(array_keys($this->changed), $this->getchangedI18N($this->getLocale()));
	}

	/**
	 * Get the changed i18n properties.
	 * @param  string $locale
	 * @return array
	 */
	public function getchangedI18N($locale=null) {
		if(!$locale)
			return array_keys($this->translationschanged);
		elseif(isset($this->translationschanged[$locale]))
			return array_keys($this->translationschanged[$locale]);
		else
			return [];
	}

	/**
	 * Reset changed properties.
	 * @return static
	 */
	public function resetchanged() {
		$this->changed             = [];
		$this->translationschanged = [];
		return $this;
	}

	/**
	 * Set a property as changed.
	 * @param string  $name
	 * @param boolean $changed
	 * @return static
	 */
	public function setChanged($name, $changed=true) {
		$this->changed[$name] = $changed;
		return $this;
	}

	/**
	 * Return a parameter.
	 * @param  string $key
	 * @return mixed
	 */
	public function getParameter($key) {
		if(!isset($this->parameters[$key]))
			return;
		return $this->parameters[$key];
	}

	/**
	 * Set a parameter.
	 * @param  string $key
	 * @param  mixed  $value
	 * @return static
	 */
	public function setParameter($key, $value) {
		$this->parameters[$key] = $value;
		return $this;
	}

	public function getClass() {
		return get_class($this);
	}
}
