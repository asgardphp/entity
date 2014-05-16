<?php
namespace Asgard\Entity\Properties;

class IntegerProperty extends BaseProperty {
	public function getSQLType() {
		if($this->length)
			return 'int('.$this->length.')';
		else
			return 'int(11)';
	}
}