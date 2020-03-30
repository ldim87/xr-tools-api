<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

class Signature
{
	/**
	 * @var string
	 */
	protected $secretKey;

	/**
	 * Signature constructor.
	 * @param string $secretKey
	 * @throws \Exception
	 */
	public function __construct(string $secretKey)
	{
		if (empty($secretKey)) {
			throw new \Exception('Secret key not specified');
		}

		$this->secretKey = $secretKey;
	}

	/**
	 * @param string $string
	 * @return string
	 */
	public function get(string $string): string
	{
		return $this->generateToken($string, 'now');
	}

	/**
	 * @param string $string
	 * @param string $hash
	 * @return bool
	 */
	public function check(string $string, string $hash): bool
	{
		$times = [
			'now',
			'-1 day'
		];

		// Проверяем токены за 2 дня
		foreach ($times as $time)
		{
			$new_hash = $this->generateToken($string, $time);

			if ($hash == $new_hash) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $string
	 * @param string $time
	 * @return string
	 */
	protected function generateToken(string $string, string $time): string
	{
		return hash(
			'sha256',
			date('dmY', strtotime($time)).'_'.$this->secretKey.'_'.$string
		);
	}
}
