<?php
namespace Asgard\Entity;

interface Collection {
	public function sync($ids);
	public function add($ids);
	public function remove($ids);
}