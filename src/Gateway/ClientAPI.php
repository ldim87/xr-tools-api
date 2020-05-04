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
	protected $apiPath = 'gateway_api';

	/**
	 * @var string  Последняя ошибка
	 */
	protected $lastError = '';

	/**
	 * @var bool Отладка
	 */
	protected $debug = false;

	/**
	 * @var array
	 */
	protected $requiredInput = [];

	/**
	 * ClientAPI constructor.
	 * @param DebugMessages $dbg
	 * @param Client $client
	 * @param array $opt
	 * - path       Путь к API по умолчанию
	 * - req_input  Обязательные параметры
	 * - debug      Отладка
	 */
	public function __construct(
		DebugMessages $dbg,
		Client $client,
		array $opt
	){
		$this->dbg = $dbg;
		$this->client = $client;

		// Путь к API по умолчанию
		if (! empty($opt['path'])) {
			$this->apiPath = $opt['path'];
		}

		// Обязательные параметры
		if (! empty($opt['req_input']) && is_array($opt['req_input'])) {
			$this->requiredInput = $opt['req_input'];
		}

		// Отладка
		if (! empty($opt['debug'])) {
			$this->debug = true;
		}
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
	 * 	- debug       Отладка
	 * 	- post_build  Кодировать POST данные в строку (по умолчанию TRUE)
	 * @return mixed
	 */
	public function query( string $path, array $input = [], array $opt = [])
	{
		// Сбрасываем последнюю ошибку
		$this->lastError = '';

		if ($this->debug) {
			$opt['debug'] = true;
		}

		$debug = ! empty($opt['debug']);

		if (empty($path)) {
			$this->lastError = 'Request path not specified';
			if ($debug)
				$this->dbg->log( $this->lastError, __METHOD__);
			return false;
		}

		// Адресуемся на закрытое API
		$path = $this->apiPath.'/'.$path;

		// Создаём структуру и добавляем обязательные параметры
		$input = $this->client->inputBuildAndMerge($input, $this->requiredInput);

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
