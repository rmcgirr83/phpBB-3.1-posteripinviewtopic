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
use phpbb\db\driver\driver_interface as db;
use phpbb\language\language;
use phpbb\request\request;
use phpbb\exception\http_exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class freegeoip
{
	/** @var auth $auth */
	protected $auth;

	/** @var db $db */
	protected $db;

	/** @var language $language */
	protected $language;

	/** @var request $request */
	protected $request;

	/** @var array phpBB tables */
	protected $tables;

	/** @var string */
	protected $err = '';

	/**
	* Constructor
	*
	* @param auth					$auth				Auth object
	* @param db						$db					Database object
	* @param language				$language			Language object
	* @param request				$request			Request object
	* @param array					$tables				phpBB db tables
	* @access public
	*/
	public function __construct(
		auth $auth,
		driver_interface $db,
		language $language,
		request $request,
		array $tables
	)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->tables = $tables;
	}

	/*
	* populate a popup to display information retrieved
	*
	* @param	$post_id		the post id
	* @return 	json response
	* @access	public
	*/
	public function freegeoip($post_id = 0)
	{
		if ($this->request->is_ajax())
		{
			$this->language->add_lang('common', 'rmcgirr83/posteripinviewtopic');
			$this->language->add_lang('mcp');

			$sql = 'SELECT poster_ip, forum_id
				FROM ' . $this->tables['posts'] . '
				WHERE post_id = ' . (int) $post_id;
			$result = $this->db->sql_query_limit($sql, 1);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			// sanity check in case someone is just inserting post_ids at random
			if (!$row)
			{
				throw new http_exception(404, 'POST_NOT_EXIST');
			}

			// auth check
			if (!$this->auth->acl_gets('a_', 'm_') || !$this->auth->acl_get('m_', (int) $row['forum_id']))
			{
				throw new http_exception(403, 'NOT_AUTHORISED');
			}

			$response = $this->freegeoip_api($row['poster_ip']);

			if (!empty($this->err))
			{
				$data = [
					'MESSAGE_TITLE'	=> $this->language->lang('ERROR'),
					'MESSAGE_TEXT'	=> $this->language->lang('ERROR_FROM_SERVER', $this->err),
				];

				$json_response = new JsonResponse($data);
				return $json_response;
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
			$json_response = new JsonResponse($data);
			return $json_response;
		}

		throw new http_exception(405, 'EXTENSION_REQUIRES_JAVASCRIPT');
	}

	/*
	* Query the freegoip database
	* @param	$posterip		the posters ip
	* @return 	string			return either a string on failure or json data
	* @access	private
	*/
	public function freegeoip_api($poster_ip)
	{
		$url = 'https://freegeoip.app/json/' . $poster_ip;

		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
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
