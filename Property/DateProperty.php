<?php
namespace Asgard\Entity\Property;

/**
 * Date Property.
 * @author Michel Hognerud <michel@hognerud.com>
 */
class DateProperty extends \Asgard\Entity\Property {
	public function _prepareValidator(\Asgard\Validation\ValidatorInterface $validator) {
		$validator->rule('isinstanceof', 'Asgard\Common\DatetimeInterface');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMessages() {
		$messages = parent::getMessages();
		$messages['instanceof'] = ':attribute must be a valid date.';

		return $messages;
	}

	/**
	 * {@inheritDoc}
	 */
	public function _getDefault() {
		return;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doSerialize($obj) {
		if(!$obj)
			return null;
		return $obj->format('Y-m-d');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function doUnserialize($str) {
		if(!$str)
			return;
		return \Asgard\Common\Date::createFromFormat('Y-m-d', $str);
	}

	/**
	 * {@inheritDoc}
	 */
	public function doSet($val, \Asgard\Entity\Entity $entity, $name) {
		if($val instanceof \Asgard\Common\DatetimeInterface)
			return $val;
		elseif(is_string($val))
			return \Asgard\Common\Date::createFromFormat('Y-m-d', $val);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toString($obj) {
		return $obj->format('Y-m-d');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFormField() {
		return 'Asgard\Form\Field\DateField';
	}

	/**
	 * Return parameters for ORM.
	 * @return array
	 */
	public function getORMParameters() {
		return [
			'type' => 'date',
		];
	}

	/**
	 * Return prepared input for SQL.
	 * @param  mixed $val
	 * @return string
	 */
	public function toSQL($val) {
		return $val->format('Y-m-d');
	}

	/**
	 * Transform SQL output.
	 * @param  mixed $val
	 * @return boolean
	 */
	public function fromSQL($val) {
		return $this->doUnserialize($val);
	}
}