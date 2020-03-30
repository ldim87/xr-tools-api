<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

class KeyGenerator
{
	/**
	 * @var string
	 */
	protected $secret_key;

	/**
	 * KeyGenerator constructor.
	 * @param string $secret_key
	 * @throws \Exception
	 */
	public function __construct(string $secret_key)
	{
		if (empty($secret_key)) {
			throw new \Exception('Secret key not specified');
		}

		$this->secret_key = $secret_key;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function get(string $name): string
	{
		if (empty($name)) {
			return false;
		}

		return hash('sha256', $name.'_'.$this->secret_key);
	}
}
