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
		protected DebugMessages $dbg,
		protected Client $client,
		protected array $opt
	){
		// Путь к API по умолчанию
		if (! empty($this->opt['path'])) {
			$this->apiPath = $this->opt['path'];
		}

		// Обязательные параметры
		if (! empty($this->opt['req_input']) && is_array($this->opt['req_input'])) {
			$this->requiredInput = $this->opt['req_input'];
		}

		// Отладка
		if (isset($this->opt['debug'])) {
			$this->debug = !! $this->opt['debug'];
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
	 * @param string $path Путь
	 * @param array $input Данные (POST по умолчанию)
	 * @param array $opt Опции
	 *    - debug       Отладка
	 *    - post_build  Кодировать POST данные в строку (по умолчанию TRUE)
	 * @return mixed
	 * @throws \Exception
	 */
	public function query( string $path, array $input = [], array $opt = [])
	{
		// Сбрасываем последнюю ошибку
		$this->lastError = '';
		$exception = ! empty($this->opt['exception']) || ! empty($opt['exception']);

		$debug = $opt['debug'] ?? $this->debug;

		if (empty($path))
		{
			$this->lastError = 'Request path not specified';

			if ($debug) {
				$this->dbg->log( $this->lastError, __METHOD__);
			}

			if ($exception) {
				throw new \Exception($this->lastError);
			}

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
		if (empty($result))
		{
			$this->lastError = 'Gateway error';

			if ($exception) {
				throw new \Exception($this->lastError);
			}

			return false;
		}

		// Если не валидный ответ API
		if (! isset($result['status']) && ! isset($result['message']) && ! isset($result['response']))
		{
			$this->lastError = 'Invalid API response';

			if ($debug) {
				$this->dbg->log( $this->lastError, __METHOD__);
			}

			if ($exception) {
				throw new \Exception($this->lastError);
			}

			return false;
		}

		// Если ошибка в ответе API
		if (! $result['status'])
		{
			$this->lastError = $result['message'];

			if ($debug) {
				$this->dbg->log( $this->lastError, __METHOD__);
			}

			if ($exception) {
				throw new \Exception($this->lastError);
			}

			return false;
		}

		return $result['response'];
	}
}
