<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools\Gateway;

use \XrTools\Utils\DebugMessages;

class ClientAPI
{
	/**
	 * @var DebugMessages
	 */
	protected $dbg;

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var string  Путь к api с которым работаем
	 */
	protected $apiPath;

	/**
	 * @var string  Последняя ошибка
	 */
	protected $lastError = '';

	/**
	 * ClientAPI constructor.
	 * @param DebugMessages $dbg
	 * @param Client $client
	 * @param array $opt
	 */
	public function __construct(
		DebugMessages $dbg,
		Client $client,
		array $opt
	){
		$this->dbg = $dbg;
		$this->client = $client;
		
		if (empty($opt['path'])) {
			$opt['path'] = 'gateway_api';
		}
		
		$this->apiPath = $opt['path'];
	}

	/**
	 * Последняя ошибка
	 * @return string
	 */
	public function lastError(): string
	{
		return $this->lastError;
	}

	/**
	 * Запрос к API
	 * @param  string  $path   Путь
	 * @param  array   $input  Данные (POST по умолчанию)
	 * @param  array   $opt    Опции
	 * 	- debug          Отладка
	 * 	- no_post_build  Не кодировать POST данные в строку
	 * @return mixed
	 */
	public function query( string $path, array $input = [], array $opt = [])
	{
		$this->lastError = '';

		$debug = ! empty($opt['debug']);

		if (empty($path)) {
			$this->lastError = 'Request path not specified';
			if ($debug)
				$this->dbg->log( $this->lastError, __METHOD__);
			return false;
		}

		// Адресуемся на закрытое API
		$path = $this->apiPath.'/'.$path;

		// Работаем только с JSON
		$opt['json'] = true;

		// Делаем запрос
		$result = $this->client->query($path, $input, $opt);

		// Если ошибка на уровне шлюза
		if (empty($result)) {
			$this->lastError = 'Gateway error';
			return false;
		}

		// Если не валидный ответ API
		if (! isset($result['status']) && ! isset($result['message']) && ! isset($result['response'])) {
			$this->lastError = 'Invalid API response';
			if ($debug)
				$this->dbg->log( $this->lastError, __METHOD__);
			return false;
		}

		// Если ошибка в ответе API
		if (! $result['status']) {
			$this->lastError = $result['message'];
			if ($debug)
				$this->dbg->log( $this->lastError, __METHOD__);
			return false;
		}

		return $result['response'];
	}
}
