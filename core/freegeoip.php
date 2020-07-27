<?php
/**
*
* @package phpBB Extension - Poster IP in viewtopic
* @copyright (c) 2020 RMcGirr83 (Rich McGirr)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace rmcgirr83\posteripinviewtopic\core;

/**
* Ignore
*/
use phpbb\auth\auth;
use phpbb\language\language;
use phpbb\request\request;
use phpbb\exception\http_exception;

class freegeoip
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth					$auth			Auth object
	* @param \phpbb\language\language			$language		Language object
	* @param \phpbb\request\request				$request		Request object
	* @access public
	*/
	public function __construct(
		auth $auth,
		language $language,
		request $request
	)
	{
		$this->auth = $auth;
		$this->language = $language;
		$this->request = $request;

		//a variable we use later...maybe
		$this->err = '';
	}

	/*
	* populate a popup to display information retrieved
	*
	* @param	$posterip		the posters ip
	* @param	$forum_id		the forum id
	* @return 	json response
	* @access	public
	*/
	public function freegeoip($poster_ip = '127.0.0.1', $forum_id = 0)
	{

		$poster_ip = $poster_ip;
		$forum_id = (int) $forum_id;

		$this->language->add_lang('common', 'rmcgirr83/posteripinviewtopic');

		if (empty($poster_ip) || $poster_ip == '127.0.0.1')
		{
			throw new http_exception(403, 'IP_ADDRESS_INVALID');
		}

		if (!$this->auth->acl_gets('a_', 'm_') || !$this->auth->acl_get('m_', (int) $forum_id))
		{
			throw new http_exception(403, 'NOT_AUTHORISED');
		}

		if ($this->request->is_ajax())
		{
			$response = $this->freegeoip_api($poster_ip);

			if (!empty($this->err))
			{
				$data = [
					'MESSAGE_TITLE'	=> $this->language->lang('ERROR'),
					'MESSAGE_TEXT'	=> $this->language->lang('ERROR_FROM_SERVER', $this->err),
				];

				$json_response = new \phpbb\json_response;
				$json_response->send($data);
			}

			$json_decode = json_decode($response, true);
			$message = '';
			foreach ($json_decode as $key => $value)
			{
				if ($key != 'metro_code' && !empty($value))
				{
					$message .= '<pre>' . $this->language->lang('PIPIV_' . strtoupper($key), $value) . '</pre>';
				}
			}

			$data = [
				'MESSAGE_TITLE' => $this->language->lang('PAGE_TITLE'),
				'MESSAGE_TEXT'	=> $message,
				'success'	=> true,
			];
			$json_response = new \phpbb\json_response;
			$json_response->send($data);
		}

		throw new http_exception(405, 'EXTENSION_REQUIRES_JAVASCRIPT');
	}

	/*
	* Query the freegoip database
	* @param	$posterip		the posters ip
	* @param	$forum_id		the forum id
	* @return 	string			return either a string on success or false on failure
	* @access	private
	*/
	public function freegeoip_api($poster_ip)
	{
		$url = 'https://freegeoip.app/json/' . $poster_ip;

		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => [
			"accept: application/json",
			"content-type: application/json"
			],
		]);

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if (!empty($err))
		{
			$this->err = $err;
		}
		else
		{
			return $response;
		}
	}
}
