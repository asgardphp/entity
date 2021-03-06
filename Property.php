<?php
namespace Asgard\Entity;

use SuperClosure\SerializableClosure;

/**
 * Entity definition property.
 * @author Michel Hognerud <michel@hognerud.com>
 */
class Property {
	/**
	 * Entity definition.
	 * @var Definition
	 */
	protected $definition;
	/**
	 * Property name.
	 * @var string
	 */
	protected $name;
	/**
	 * Parameters.
	 * @var array
	 */
	protected $params = [];

	/**
	 * Constructor.
	 * @param array $params
	 */
	public function __construct(array $params) {
		$this->params = $params;
	}

	public function getFormParameters() {
		$params = $this->get('form');
		if($this->get('in'))
			$params['choices'] = $this->get('in');
		
		return $params;
	}

	/**
	 * __sleep magic method.
	 * @return string[]
	 */
	public function __sleep() {
		foreach($this->params as $k=>$v) {
			if($v instanceof \Closure)
				$this->params[$k] = new SerializableClosure($v);
		}
		return ['definition', 'name', 'params'];
	}

	/**
	 * Set property position.
	 * @param integer $position
	 */
	public function setPosition($position) {
		$this->params['position'] = $position;
		return $this;
	}

	/**
	 * Get the property position.
	 * @return integer
	 */
	public function getPosition() {
		return isset($this->params['position']) ? $this->params['position']:null;
	}

	/**
	 * Set the name.
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Set the definition.
	 * @param Definition $definition
	 */
	public function setDefinition($definition) {
		$this->definition = $definition;
	}

	/**
	 * Check if entity is required.
	 * @return boolean
	 */
	public function required() {
		if(isset($this->params['required']))
			return !!$this->params['required'];
		if(isset($this->params['validation']['required']))
			return !!$this->params['validation']['required'];
	}

	/**
	 * Get a parameter.
	 * @param  string $path
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function get($path, $default=null) {
		if(!$this->has($path))
			return $default;
		return \Asgard\Common\ArrayUtils::get($this->params, $path);
	}

	/**
	 * Set a parameter.
	 * @param  string $path
	 * @param  mixed  $value
	 */
	public function set($path, $value) {
		\Asgard\Common\ArrayUtils::set($this->params, $path, $value);
	}

	/**
	 * Check if has a parameter.
	 * @param  string  $path
	 * @return boolean
	 */
	public function has($path) {
		return \Asgard\Common\ArrayUtils::has($this->params, $path);
	}

	/**
	 * Return parameters.
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * Return the name.
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * __toString magic method.
	 * @return string
	 */
	public function __toString() {
		return $this->getName();
	}

	/**
	 * Return the default value.
	 * @param  Entity $entity
	 * @param  strng  $name
	 * @return mixed
	 */
	public function getDefault($entity, $name) {
		if($this->get('many'))
			return new ManyCollection($entity, $name);
		elseif(isset($this->params['default'])) {
			if(is_callable($this->params['default']))
				return $this->params['default']();
			else
				return $this->params['default'];
		}
		else
			return $this->_getDefault($entity);
	}

	/**
	 * Return the default value for a single element.
	 * @return null
	 */
	protected function _getDefault() {
		return null;
	}

	/**
	 * Prepare the validator.
	 * @return array
	 */
	public function prepareValidator(\Asgard\Validation\ValidatorInterface $validator) {
		if($this->get('required'))
			$validator->rule('required', true);
		if($this->get('length'))
			$validator->rule('maxlength', $this->get('length'));
		if($this->get('in'))
			$validator->rule('in', [array_keys($this->get('in'))]);

		if(method_exists($this, '_prepareValidator'))
			$this->_prepareValidator($validator);

		$rules = isset($this->params['validation']) ? $this->params['validation']:[];
		if(!is_array($rules))
			$rules = [$rules];
		$validator->rules($rules);
	}

	/**
	 * Return the validation messages.
	 * @return array
	 */
	public function getMessages() {
		if(isset($this->params['messages']))
			return $this->params['messages'];
		else
			return [];
	}

	/**
	 * Serialize the value.
	 * @param  mixed $val
	 * @return string
	 */
	public function serialize($val) {
		if($this->get('many')) {
			if(!$val instanceof ManyCollection)
				return serialize([]);
			$r = [];
			foreach($val as $v) {
				$s = $this->doSerialize($v);
				if($s !== null)
					$r[] = $s;
			}
			return serialize($r);
		}
		else
			return $this->doSerialize($val);
	}

	/**
	 * Actually perform serialization for a single element.
	 * @param  mixed $val
	 * @return string
	 */
	protected function doSerialize($val) {
		if(is_string($val) || is_numeric($val) || is_bool($val) || is_null($val))
			return $val;
		else
			return serialize($val);
	}

	/**
	 * Unserialize a string.
	 * @param  string $str
	 * @param  Entity $entity
	 * @param  string $name
	 * @return mixed
	 */
	public function unserialize($str, $entity, $name) {
		if($this->get('many')) {
			$r = new ManyCollection($entity, $name);
			$arr = unserialize($str);
			if(!is_array($arr))
				return $r;
			foreach($arr as $v)
				$r[] = $this->doUnserialize($v, $entity);
			return $r;
		}
		else
			return $this->doUnserialize($str, $entity);
	}

	/**
	 * Actually perform unserialization for a single element.
	 * @param  string $str
	 * @return string
	 */
	protected function doUnserialize($str) {
		return $str;
	}

	/**
	 * Pre-process value before passing it to entity.
	 * @param mixed  $val
	 * @param Entity $entity
	 * @param string $name
	 * @param boolean  $silentException
	 * @return mixed
	 */
	public function setDecorator($val, Entity $entity, $name, $silentException=false) {
		if($this->get('many')) {
			if($val instanceof ManyCollection)
				return $val;
			if(is_array($val)) {
				$res = new ManyCollection($entity, $name);
				foreach($val as $v)
					$res[] = $this->_doSet($v, $entity, $name, $silentException);
				return $res;
			}
			else
				return $val;
		}
		else
			return $this->_doSet($val, $entity, $name, $silentException);
	}

	/**
	 * Catch exceptions of doSet if required.
	 * @param  mixed  $val
	 * @param  Entity $entity
	 * @param  string $name
	 * @param boolean  $silentException
	 * @return mixed
	 */
	public function _doSet($val, Entity $entity, $name, $silentException=false) {
		if($silentException) {
			try {
				return $this->doSet($val, $entity, $name);
			} catch(\Exception $e) {
				return null;
			} catch(\Throwable $e) {
				return null;
			}
		}
		else
			return $this->doSet($val, $entity, $name);
	}

	/**
	 * Actually pre-process value for a single element.
	 * @param  mixed  $val
	 * @param  Entity $entity
	 * @param  string $name
	 * @return mixed
	 */
	public function doSet($val, Entity $entity, $name) {
		return $val;
	}
}