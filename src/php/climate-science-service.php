<?php
	/*
	* Climate Science Service: a PHP REST service for querying a climate science database.
	* Copyright © 2023 Adrian Price. All rights reserved.
	* Licensed under the GNU Affero General Public License v.3 https://www.gnu.org/licenses/agpl-3.0.html
	*/

	const JWT_SECRET = 'JWT_SECRET'; // JWT signing key.
	const JWT_TTL = 'JWT_TTL'; // JWT lifetime.
	const DB_URL = 'DB_URL'; // Database URL.
	const DB_USER = 'DB_USER'; // Database user.
	const DB_PASSWORD = 'DB_PASSWORD'; // Database password.
	const AUTHORIZATION = 'Authorization';
	const DELETE = 'DELETE';
	const GET = 'GET';
	const OPTIONS = 'OPTIONS';
	const PATCH = 'PATCH';
	const PUT = 'PUT';
	const POST = 'POST';
	const PARAM_FILTER = 'filter';
	const PARAM_START = 'start';
	const PARAM_COUNT = 'count';
	const PARAM_LAST_NAME = 'lastName';
	const PARAM_PERSON_ID = 'personId';
	const PARAM_PUBLICATION_ID = 'publicationId';
	const PARAM_DECLARATION_ID = 'declarationId';
	const PARAM_QUOTATION_ID = 'quotationId';
	const PARAM_TOPIC = 'topic';
	// @formatter:off
	const FIND_DEFAULTS = [
		PARAM_FILTER => null,
		PARAM_START => 0,
		PARAM_COUNT => 0
	];
	const USER_DEFAULTS = [
		PARAM_PERSON_ID => null,
		PARAM_LAST_NAME => null
	];
	const AUTHORSHIP_DEFAULTS = [
		PARAM_PERSON_ID => null,
		PARAM_PUBLICATION_ID => null
	];
	const SIGNATORY_DEFAULTS = [
		PARAM_PERSON_ID => null,
		PARAM_DECLARATION_ID => null
	];
	const STATS_DEFAULTS = [
		PARAM_TOPIC => null
	];
	const PERSON_FIELDS = [
		'ID',
		'TITLE',
		'FIRST_NAME',
		'NICKNAME',
		'PREFIX',
		'LAST_NAME',
		'SUFFIX',
		'ALIAS',
		'DESCRIPTION',
		'QUALIFICATIONS',
		'COUNTRY',
		'RATING',
		'CHECKED',
		'PUBLISHED'
	];
	const PUBLICATION_FIELDS = [
		'ID',
		'TITLE',
		'AUTHORS',
		'JOURNAL',
		'PUBLICATION_TYPE_ID',
		'PUBLICATION_DATE',
		'PUBLICATION_YEAR',
		'PEER_REVIEWED',
		'DOI',
		'ISSN_ISBN',
		'URL',
		'ACCESSED'
	];
	const DECLARATION_FIELDS = [
		'ID',
		'TYPE',
		'TITLE',
		'DATE',
		'COUNTRY',
		'URL',
		'SIGNATORIES',
		'SIGNATORY_COUNT',
	];
	const QUOTATION_FIELDS = [
		'ID',
		'PERSON_ID',
		'AUTHOR',
		'TEXT',
		'DATE',
		'SOURCE',
		'URL',
	];
	// @formatter:on
	const MIME_TYPE_APPLICATION_JSON = 'application/json';
	const MIME_TYPE_APPLICATION_XML = 'application/xml';

	require './StatusCode.php';
	use PH7\JustHttp\StatusCode;

	/** Used to return multi-row results. */
	class ResultSet {
		public $count = 0;
		public $records = [];
	}

	/** Used to return a ResultSet count. */
	class Count {
		public $COUNT = 0;
	}

	try {
		readenv();

		// When running as CLI, HTTP method and request URI are passed as command line arguments.
		if (isCli()) {
			if ($argc != 3)
				throw new Error("Invalid argument(s)");

			$_SERVER['SERVER_ADDR'] = '192.168.0.110';
			$_SERVER['REMOTE_ADDR'] = '192.168.0.110';
			$_SERVER['REQUEST_METHOD'] = strtoupper($argv[1]);
			$_SERVER['REQUEST_URI'] = $argv[2];
		}
		preg_match('#^/climate-science-service/([^?]*)(?:\?(.*))?$#', $_SERVER['REQUEST_URI'], $matches);
		$matchCount = count($matches);
		$method = $_SERVER['REQUEST_METHOD'];
		$path = $matchCount > 0 ? explode('/', $matches[1]) : [];
		if ($matchCount > 1 && isset($matches[2]))
			parse_str($matches[2], $params);
		else
			$params = [];

		// Handle request via a tree of dispath methods.
		$result = dispatchRequest($method, $path, $params, $status);

		header('Title: Campaign Resources | Climate Science Service');
		// REST service result is formatted as JSON.
		header('Content-type: application/json');

		// To support testing by Swagger Editor:
		// In Firefox about:config, set security.mixed_content.block_active_content=false
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Headers: Authorization, Content-Type');

		http_response_code($status);

		// If there is a result, emit it to the body
		if (isset($result))
			echo json_encode($result);
	} catch (Throwable $e) {
		header('Access-Control-Allow-Origin: *');
		http_response_code(StatusCode::INTERNAL_SERVER_ERROR);
		echo 'Error: ' . $e->getMessage();
	}

	/**
	 * Dispatches a REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 1, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		switch ($path[0]) {
			case 'auth':
				$result = dispatchAuthRequest($method, $path, $params, $status);
				break;
			case 'person':
				$result = dispatchPersonRequest($method, $path, $params, $status);
				break;
			case 'publication':
				$result = dispatchPublicationRequest($method, $path, $params, $status);
				break;
			case 'declaration':
				$result = dispatchDeclarationRequest($method, $path, $params, $status);
				break;
			case 'quotation':
				$result = dispatchQuotationRequest($method, $path, $params, $status);
				break;
			case 'authorship':
				$result = dispatchAuthorshipRequest($method, $path, $params, $status);
				break;
			case 'signatory':
				$result = dispatchSignatoryRequest($method, $path, $params, $status);
				break;
			case 'statistics':
				$result = dispatchStatisticsRequest($method, $path, $params, $status);
				break;
			default:
				$status = StatusCode::BAD_REQUEST;
				$result = null;
				break;
		}
		return $result;
	}

	/**
	 * Dispatches an auth-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchAuthRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# POST /auth/login => login()
		switch ($method) {
			case POST:
				switch ($path[1]) {
					case 'login':
						$result = login($status);
						break;
					default:
						$status = StatusCode::BAD_REQUEST;
						break;
				}
				break;
			case OPTIONS:
				$result = emitWriteOptions('POST, OPTIONS', $status);
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Person-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchPersonRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /person/{personId} => getPersonById($personId)
		# GET /person/find?filter=&start=0&count=0 => findPersons($filter, $start, $count)
		# GET /person/findByPublication?publicationId=0&filter=&start=0&count=0 => findPersonsByPublication($publicationId, $filter, $start, $count)
		# GET /person/findByDeclaration?declarationId=0&filter=&start=0&count=0 => findPersonsByDeclaration($declarationId, $filter, $start, $count)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersons($params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByPublication':
						setDefault($params, PARAM_PUBLICATION_ID, null);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersonsByPublication($params[PARAM_PUBLICATION_ID], $params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByDeclaration':
						setDefault($params, PARAM_PUBLICATION_ID, null);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersonsByDeclaration($params[PARAM_DECLARATION_ID], $params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default:
						$result = getPersonById($path[1], $status);
						break;
				}
				break;
			case OPTIONS:
				$result = emitWriteOptions('GET, OPTIONS', $status);
				break;
			default :
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Publication-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchPublicationRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /publication/{publicationId} => getPublicationById($publicationId)
		# GET /publication/find?filter=&start=0&count=0 => findPublications($filter, $start, $count)
		# GET /publication/findByAuthor?personId=0&lastName=&filter=&start=0&count=0 => findPublicationsByAuthor($personId, $lastName, $filter, $start, $count)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findPublications($params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByAuthor':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPublicationsByAuthor($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default:
						$result = getPublicationById($path[1], $status);
						break;
				}
				break;
			case OPTIONS:
				$result = emitWriteOptions('GET, OPTIONS', $status);
				break;
			default :
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Declaration-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchDeclarationRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /declaration/{declarationId} => getDeclarationById($declarationId)
		# GET /declaration/find?start=0&count=10 => findDeclarations($start, $count)
		# GET /declaration/findBySignatory?personId=0&lastName=author&start=0&count=10 => findDeclarationsBySignatory($personId, $lastName, $start, $count)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findDeclarations($params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findBySignatory':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findDeclarationsBySignatory($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default :
						$result = getDeclarationById($path[1], $status);
						break;
				}
				break;
			case OPTIONS:
				$result = emitWriteOptions('GET, OPTIONS', $status);
				break;
			default :
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Quotation-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchQuotationRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /quotation/{quotationId} => getQuotationById($quotationId)
		# PATCH /quotation/{quotationId}?personId=0 => linkQuotationAuthor($quotationId, $personId)
		# GET /quotation/find?start=0&count=10 => findQuotations($start, $count)
		# GET /quotation/findByAuthor?personId=0&lastName=author;start=0&count=10 => findQuotationsByAuthor($personId, $lastName, $start, $count)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findQuotations($params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByAuthor':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findQuotationsByAuthor($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_FILTER], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default :
						$result = getQuotationById($path[1], $status);
						break;
				}
				break;
			case PATCH:
				if (authoriseWriteRequest($method, $status))
					$result = linkQuotationAuthor($path[1], $params[PARAM_PERSON_ID], $status);
				break;
			case OPTIONS:
				$result = emitWriteOptions('GET, PATCH, OPTIONS', $status);
				break;
			default :
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches an Authorship-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchAuthorshipRequest($method, $path, $params, &$status) {
		// Disallow authorship updates from unauthenticated clients.
		if (!authoriseWriteRequest($method, $status))
			return null;

		if (!checkArray($path, 3, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# PUT /authorship/{personId}/{publicationId} => createAuthorship
		# DELETE /authorship/{personId}/{publicationId} => deleteAuthorship
		setDefaults($params, AUTHORSHIP_DEFAULTS);
		switch ($method) {
			case PUT:
				$result = createAuthorship($path[1], $path[2], $status);
				break;
			case DELETE:
				$result = deleteAuthorship($path[1], $path[2], $status);
				break;
			case OPTIONS:
				$result = emitWriteOptions('PUT, DELETE, OPTIONS', $status);
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Signatory-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchSignatoryRequest($method, $path, $params, &$status) {
		// Disallow signatory updates from unauthenticated clients.
		if (!authoriseWriteRequest($method, $status))
			return null;

		if (!checkArray($path, 3, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# PUT /signatory/{personId}/{declarationId} => createSignatory
		# DELETE /signatory/{personId}/{declarationId} => deleteSignatory
		setDefaults($params, SIGNATORY_DEFAULTS);
		switch ($method) {
			case PUT:
				$result = createSignatory($path[1], $path[2], $status);
				break;
			case DELETE:
				$result = deleteSignatory($path[1], $path[2], $status);
				break;
			case OPTIONS:
				$result = emitWriteOptions('PUT, DELETE, OPTIONS', $status);
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches a Statistics-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
   function dispatchStatisticsRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		setDefaults($params, FIND_DEFAULTS);
		setDefaults($params, STATS_DEFAULTS);
		switch ($method) {
			case GET:
				switch ($path[1]) {
					# GET /statistics/find?topic=climate&start=0&count=0
					case 'find':
						$result = findStatistics($params[PARAM_TOPIC], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default:
						$status = StatusCode::BAD_REQUEST;
						break;
				}
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Executes an authentication request.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return string|null A JWT bearer token.
	 */
	function login(&$status) : string|null {
		// Verify credentials passed by client.
		$credentials = getRequestBody();
		$user = verifyCredentials($credentials, $status);
		if (!$user)
			return null;

		// Return the JWT bearer token.
		$status = StatusCode::OK;
		return generateJwt($user);
	}

	/**
	 * Fetches a specified Person.
	 * @param $personId The ID of the Person to retrieve.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object|null The requested Person.
	 */
	function getPersonById($personId, &$status) {
		return executeQuery("SELECT * FROM person WHERE ID=?", [$personId], false, 0, 1, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons.
	 * @param $filter Search string.
	 * @param $start The index of the first Person to return.
	 * @param $count The maximum number of Persons to return.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersons($filter, $start, $count, &$status) {
		if ($filter) {
			$fields = implode(', ', PERSON_FIELDS);
			$predicate = "WHERE LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params = ["%$filter%"];
		} else {
			$predicate = '';
			$params = null;
		}
		$sql = [
			"SELECT COUNT(*) FROM person $predicate;",
			"SELECT * FROM person $predicate ORDER BY LAST_NAME, FIRST_NAME;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons who are authors of a specified Publication.
	 * @param $publicationId The ID of the Publication whose authors are required.
	 * @param $filter Search string.
	 * @param $start The index of the first Person to retrieve.
	 * @param $count The maximum number of Persons to retrieve.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersonsByPublication($publicationId, $filter, $start, $count, &$status) {
		$params = [$publicationId];
		if ($filter) {
			$fields = implode(', ', PERSON_FIELDS);
			$predicate = "AND LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params[] = "%$filter%";
		} else {
			$predicate = '';
		}
		$sql = [
			"SELECT COUNT(*) FROM person JOIN authorship ON authorship.PERSON_ID = person.ID WHERE authorship.PUBLICATION_ID=? $predicate;",
			"SELECT * FROM person JOIN authorship ON authorship.PERSON_ID = person.ID WHERE authorship.PUBLICATION_ID=? $predicate ORDER BY person.LAST_NAME, person.FIRST_NAME;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons who are signatories to a specified Declaration.
	 * @param $declarationId The ID of the Declaration whose signatories are required.
	 * @param $filter Search string.
	 * @param $start The index of the first Person to retrieve.
	 * @param $count The maximum number of Persons to retrieve.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersonsByDeclaration($declarationId, $filter, $start, $count, &$status) {
		$params = [$declarationId];
		if ($filter) {
			$fields = implode(', ', PERSON_FIELDS);
			$predicate = "AND LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params[] = "%$filter%";
		} else {
			$predicate = '';
		}
		$sql = [
			"SELECT COUNT(*) FROM person JOIN signatory ON signatory.PERSON_ID = person.ID WHERE signatory.DECLARATION_ID=? $predicate;",
			"SELECT * FROM person JOIN signatory ON signatory.PERSON_ID = person.ID WHERE signatory.DECLARATION_ID=? $predicate ORDER BY person.LAST_NAME, person.FIRST_NAME;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Publication.
	 * @param $publicationId The ID of the Publication to retrieve.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object|null The requested Publication.
	 */
	function getPublicationById($publicationId, &$status) {
		return executeQuery("SELECT * FROM publication WHERE ID=?", [$publicationId], false, 0, 1, $status);
	}

	/**
	 * Fetches a paginated sub-list of Publications.
	 * @param $filter Search string.
	 * @param $start The index of the first Publication to return.
	 * @param $count The maximum number of Publications to return.
	 * @return ResultSet A ResultSet containing the requested Publications.
	 */
	function findPublications($filter, $start, $count, &$status) {
		if ($filter) {
			$fields = implode(', ', PUBLICATION_FIELDS);
			$predicate = "WHERE LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params = ["%$filter%"];
		} else {
			$predicate = "";
			$params = null;
		}
		$sql = [
			"SELECT COUNT(*) FROM publication $predicate;",
			"SELECT * FROM publication $predicate ORDER BY PUBLICATION_YEAR DESC, PUBLICATION_DATE DESC;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Publications authored by a specified Person.
	 * @param $personId The ID of the Person whose Publications are required.
	 * @param $lastName The last name of the Person whose Publications are required.
	 * @param $filter Search string.
	 * @param $start The index of the first Publication to retrieve.
	 * @param $count The maximum number of Publications to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Publications.
	 */
	function findPublicationsByAuthor($personId, $lastName, $filter, $start, $count, &$status) {
		if (!isset($personId)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		$params = [$personId];
		$fields = implode(', ', PUBLICATION_FIELDS);
		if ($filter) {
			$predicate = "AND LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params[] = "%$filter%";
		} else {
			$predicate = '';
		}
		# @formatter:off
		$sqlCountById =
		  '(SELECT COUNT(*)'
		. ' FROM publication'
		. ' JOIN authorship ON authorship.PUBLICATION_ID = publication.ID'
		. ' WHERE authorship.PERSON_ID=? '
		. $predicate
		. ')';
		$sqlPublicationsByAuthorId =
		  'SELECT ' . $fields . ', TRUE AS LINKED'
		. ' FROM publication'
		. ' JOIN authorship ON authorship.PUBLICATION_ID = publication.ID'
		. ' WHERE authorship.PERSON_ID=? '
		. $predicate;
		if (isset($lastName)) {
			$sqlCountByLastName =
			  '(SELECT COUNT(*)'
			. ' FROM publication'
			. ' WHERE AUTHORS LIKE ? '
			. $predicate
			. ' AND NOT EXISTS (SELECT 0 FROM authorship WHERE authorship.PUBLICATION_ID = publication.ID AND authorship.PERSON_ID = ?))';
			$sqlPublicationsByLastName =
			  'SELECT ' . $fields . ', FALSE AS LINKED'
			. ' FROM publication'
			. ' WHERE AUTHORS LIKE ? '
			. $predicate
			. ' AND NOT EXISTS (SELECT 0 FROM authorship WHERE authorship.PUBLICATION_ID = publication.ID AND authorship.PERSON_ID = ?)';
			$plus = ' + ';
			$union = ' UNION ';
			$params[] = '%' . $lastName . '%';
			if ($filter)
				$params[] = "%$filter%";
			$params[] = $personId;
		} else {
			$sqlCountByLastName = '';
			$sqlPublicationsByLastName = '';
			$plus = '';
			$union = '';
		}
		$sql = [
			  'SELECT '
			. $sqlCountById
			. $plus
			. $sqlCountByLastName
			. ';'
			,
			  $sqlPublicationsByAuthorId
			. $union
			. $sqlPublicationsByLastName
			. ' ORDER BY PUBLICATION_YEAR DESC, PUBLICATION_DATE DESC;'
		];
		# @formatter:on
		
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Declaration.
	 * @param $declarationId The ID of the Declaration to retrieve.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object|null The requested Declaration.
	 */
	function getDeclarationById($declarationId, &$status) {
		return executeQuery("SELECT * FROM declaration WHERE ID=?", [$declarationId], false, 0, 1, $status);
	}
	
	/**
	 * Fetches a paginated sub-list of Declarations.
	 * @param $filter Search string.
	 * @param $start The index of the first Declaration to return.
	 * @param $count The maximum number of Declarations to return.
	 * @return ResultSet A ResultSet containing the requested Declarations.
	 */
	function findDeclarations($filter, $start, $count, &$status) {
		if ($filter) {
			$fields = implode(', ', DECLARATION_FIELDS);
			$predicate = "WHERE LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params = ["%$filter%"];
		} else {
			$predicate = "";
			$params = null;
		}
		$sql = [
			"SELECT COUNT(*) FROM declaration $predicate;",
			"SELECT * FROM declaration $predicate ORDER BY DATE DESC;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Declarations signed by a specified Person.
	 * @param $personId The ID of the Person whose Declarations are required.
	 * @param $lastName The specified Person's last name.
	 * @param $filter Search string.
	 * @param $start The index of the first Declaration to retrieve.
	 * @param $count The maximum number of Declarations to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Declarations.
	 */
	function findDeclarationsBySignatory($personId, $lastName, $filter, $start, $count, &$status) {
		if (!isset($personId)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		$params = [$personId];
		$fields = implode(', ', DECLARATION_FIELDS);
		if ($filter) {
			$predicate = "AND LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params[] = "%$filter%";
		} else {
			$predicate = '';
		}
		# @formatter:off
		$sqlCountById =
		  '(SELECT COUNT(*)'
		. ' FROM declaration'
		. ' JOIN signatory ON signatory.DECLARATION_ID = declaration.ID'
		. ' WHERE signatory.PERSON_ID=? '
		. $predicate
		. ')';
		$sqlDeclarationsBySignatoryId =
		  'SELECT ' . $fields . ', TRUE AS LINKED'
		. ' FROM declaration'
		. ' JOIN signatory ON signatory.DECLARATION_ID = declaration.ID'
		. ' WHERE signatory.PERSON_ID=? '
		. $predicate;
		if (isset($lastName)) {
			$sqlCountByLastName =
				'(SELECT COUNT(*)'
				. ' FROM declaration'
				. ' WHERE SIGNATORIES LIKE ? '
				. $predicate
				. ' AND NOT EXISTS (SELECT 0 FROM signatory WHERE signatory.DECLARATION_ID = declaration.ID AND signatory.PERSON_ID = ?))';
				$sqlDeclarationsByLastName =
				  'SELECT ' . $fields . ', FALSE AS LINKED'
				. ' FROM declaration'
				. ' WHERE SIGNATORIES LIKE ?'
				. $predicate
				. '  AND NOT EXISTS (SELECT 0 FROM signatory WHERE signatory.DECLARATION_ID = declaration.ID AND signatory.PERSON_ID = ? '
				. ')';
				$plus = ' + ';
			$union = ' UNION ';
			$params[] = '%' . $lastName . '%';
			if ($filter)
				$params[] = "%$filter%";
			$params[] = $personId;
		} else {
			$sqlCountByLastName = '';
			$sqlDeclarationsByLastName = '';
			$plus = '';
			$union = '';
		}
		$sql = [
			  'SELECT '
			. $sqlCountById
			. $plus
			. $sqlCountByLastName
			. ';'
			,
			  $sqlDeclarationsBySignatoryId
			. $union
			. $sqlDeclarationsByLastName
			. ' ORDER BY DATE DESC;'
		];
		# @formatter:on

		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Quotation.
	 * @param $quotationId The ID of the Quotation to retrieve.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object|null The requested Quotation.
	 */
	function getQuotationById($quotationId, &$status) {
		return executeQuery("SELECT * FROM quotation WHERE ID=?", [$quotationId], false, 0, 1, $status);
	}

	/**
	 * Links or unlinks a specified Person as the origin of a specified Quotation.
	 * @param $quotationId The ID of the Quotation.
	 * @param $personId The ID of the Person to link, pass null to unlink.
	 * @return null null always.
	 */
	function linkQuotationAuthor($quotationId, $personId, &$status) {
		$result = executeUpdate("UPDATE quotation SET PERSON_ID=? WHERE ID=?", [$personId, $quotationId], $status);
		$status = $result ? StatusCode::OK : StatusCode::NOT_FOUND;
		return null;
	}

	/**
	 * Fetches a paginated sub-list of Quotations.
	 * @param $filter Search string.
	 * @param $start The index of the first Quotation to return.
	 * @param $count The maximum number of Quotations to return.
	 * @return ResultSet A ResultSet containing the requested Quotations.
	 */
	function findQuotations($filter, $start, $count, &$status) {
		if ($filter) {
			$fields = implode(', ', QUOTATION_FIELDS);
			$predicate = "WHERE LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params = ["%$filter%"];
		} else {
			$predicate = "";
			$params = null;
		}
		$sql = [
				"SELECT COUNT(*) FROM quotation $predicate;",
				"SELECT * FROM quotation $predicate ORDER BY DATE DESC;"
		];
		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Quotations authored by a specified Person.
	 * @param $personId The ID of the Person whose Quotations are required.
	 * @param $filter Search string.
	 * @param $start The index of the first Quotation to retrieve.
	 * @param $count The maximum number of Quotations to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Quotations.
	 */
	function findQuotationsByAuthor($personId, $lastName, $filter, $start, $count, &$status) {
		if (!isset($personId)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		$fields = implode(', ', QUOTATION_FIELDS);
		$params = [$personId];
		if ($filter) {
			$predicate = "AND LOWER(CONVERT(CONCAT_WS('|', $fields) USING UTF8)) LIKE ?";
			$params[] = "%$filter%";
		} else {
			$predicate = '';
		}
		# @formatter:off
		$sqlCountById =
			  'SELECT COUNT(*)'
			. ' FROM quotation'
			. ' WHERE PERSON_ID=? '
			. $predicate;
			$sqlQuotationsByAuthorId =
			  'SELECT ' . $fields . ', TRUE AS LINKED'
			. ' FROM quotation'
			. ' WHERE PERSON_ID=? '
			. $predicate;
		if (isset($lastName)) {
			$sqlCountByLastName =
				'AUTHOR LIKE ? AND PERSON_ID IS NULL '
				. $predicate;
			$sqlQuotationsByLastName =
				  'SELECT ' . $fields . ', FALSE AS LINKED'
				. ' FROM quotation'
				. ' WHERE AUTHOR LIKE ? AND PERSON_ID IS NULL '
				. $predicate;
			$or = ' OR ';
			$union = ' UNION ';
			$params[] = '%' . $lastName . '%';
			if ($filter)
				$params[] = "%$filter%";
		} else {
			$sqlCountByLastName = '';
			$sqlQuotationsByLastName = '';
			$or = '';
			$union = '';
		}
		$sql = [
			  $sqlCountById
			. $or
			. $sqlCountByLastName
			. ';'
			,
			  $sqlQuotationsByAuthorId
			. $union
			. $sqlQuotationsByLastName
			. ' ORDER BY DATE DESC;'
		];
		# @formatter:on

		return executeQuery($sql, $params, true, $start, $count, $status);
	}

	/**
	 * Links a specified Person as the author of a specified Publication.
	 * @param $personId The ID of the Person to link.
	 * @param $publicationId The ID of the Publication.
	 * @return string|null The URI of the new authorship if created or null if it was deleted.
	 */
	function createAuthorship($personId, $publicationId, &$status) {
		$sql = 'SELECT COUNT(*) AS COUNT FROM authorship WHERE PERSON_ID=? AND PUBLICATION_ID=?';
		$params = [$personId, $publicationId];
		$result = executeQuery($sql, $params, false, 0, 1, $status);
		if ($result->COUNT == 0) {
			$sql = 'INSERT INTO authorship (PERSON_ID, PUBLICATION_ID) VALUES (?, ?)';
			executeUpdate($sql, $params, $status);
			$result = "/authorship/{$personId}/{$publicationId}";
			$status = StatusCode::CREATED;
		} else {
			 // NOTE: the create link operation is idempotent.
			$result = null;
		}
		return $result;
	}

	/**
	 * Unlinks a specified Person as the author of a specified Publication.
	 * @param $personId The ID of the Person to unlink.
	 * @param $publicationId The ID of the Publication.
	 * @return null null always.
	 */
	function deleteAuthorship($personId, $publicationId, &$status) {
		$sql = 'SELECT COUNT(*) AS COUNT FROM authorship WHERE PERSON_ID=? AND PUBLICATION_ID=?';
		$params = [$personId, $publicationId];
		$count = executeQuery($sql, $params, false, 0, 1, $status);
		if ($count->COUNT == 1) {
			$sql = 'DELETE FROM authorship WHERE PERSON_ID=? AND PUBLICATION_ID=?';
			executeUpdate($sql, $params, $status);
			$status = StatusCode::OK;
		}
		// NOTE: the delete link operation is idempotent.
		return null;
	}

	/**
	 * Links a specified Person as a signatory of a specified Declaration.
	 * @param $personId The ID of the Person to link.
	 * @param $declarationId The ID of the Declaration.
	 * @return string|null.
	 */
	function createSignatory($personId, $declarationId, &$status) {
		$sql = 'SELECT COUNT(*) AS COUNT FROM signatory WHERE PERSON_ID=? AND DECLARATION_ID=?';
		$params = [$personId, $declarationId];
		$result = executeQuery($sql, $params, false, 0, 1, $status);
		if ($result->COUNT == 0) {
			$sql = 'INSERT INTO signatory (PERSON_ID, DECLARATION_ID) VALUES (?, ?)';
			executeUpdate($sql, $params, $status);
			$result = "/signatory/{$personId}/{$declarationId}";
			$status = StatusCode::CREATED;
 		} else {
 			// NOTE: the create link operation is idempotent.
 			$result = null;
		}
		return $result;
	}

	/**
	 * Unlinks a specified Person as a signatory of a specified Declaration.
	 * @param $personId The ID of the Person to unlink.
	 * @param $declarationId The ID of the Declaration.
	 * @return null null always.
	 */
	function deleteSignatory($personId, $declarationId, &$status) {
		$sql = 'SELECT COUNT(*) AS COUNT FROM signatory WHERE PERSON_ID=? AND DECLARATION_ID=?';
		$params = [$personId, $declarationId];
		$result = executeQuery($sql, $params, false, 0, 1, $status);
		if ($result->COUNT == 1) {
			$sql = 'DELETE FROM signatory WHERE PERSON_ID=? AND DECLARATION_ID=?';
			executeUpdate($sql, $params, $status);
			$status = StatusCode::OK;
		}
		// NOTE: the delete link operation is idempotent.
		return null;
	}

	/**
	 * Returns database statistics about the specified topic.
	 * @param $topic The topic for which metrics are requested.
	 * @param $start The index of the first Statistic to retrieve.
	 * @param $count The maximum number of Statistics to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Statistics.
	 */
	function findStatistics($topic, $start, $count, &$status) {
		if (!$topic) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		switch ($topic) {
			case 'climate':
				$result = findClimateStatistics($start, $count, $status);
				break;
			default:
				$status = StatusCode::BAD_REQUEST;
		}
		return $result;
	}

	/**
	 * Returns database statistics about the climate topic.
	 * @param $start The index of the first Statistic to retrieve.
	 * @param $count The maximum number of Statistics to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Statistics.
	 */
	function findClimateStatistics($start, $count, &$status) {
		$countSql = "SELECT 14 AS COUNT";
		$selectSql =
			"SELECT 'Persons' AS `CATEGORY`, COUNT(*) AS `COUNT`, 'Total number of people in the database' AS DESCRIPTION FROM person UNION "
		  . "SELECT 'Publications', COUNT(*), 'Total number of publications in the database' FROM publication UNION "
		  . "SELECT 'Declarations', COUNT(*), 'Total number of public declarations in the database' FROM declaration UNION "
		  . "SELECT 'Quotations', COUNT(*), 'Total number of quotations in the database' FROM quotation UNION "
		  . "SELECT 'Professors', COUNT(*), 'Number of university professors (past or present)' FROM person WHERE TITLE='Prof.' UNION "
		  . "SELECT 'Doctorates', COUNT(*), 'Number qualified to doctoral or higher level (additional to professors)' FROM person WHERE TITLE='Dr.' UNION "
		  . "SELECT 'Meteorologists', COUNT(*), 'Number of qualified meterologists' FROM person WHERE DESCRIPTION LIKE '%meteorolog%' OR DESCRIPTION LIKE '%weather%' UNION "
		  . "SELECT 'Climatologists', COUNT(*), 'Number of climatologists' FROM person WHERE DESCRIPTION LIKE '%climatolog%' UNION "
		  . "SELECT 'IPCC', COUNT(*), 'Number of scientists who work(ed) for IPCC' FROM person WHERE DESCRIPTION LIKE '%IPCC%' AND DESCRIPTION NOT LIKE '%NIPCC%' UNION "
		  . "SELECT 'NASA', COUNT(*), 'Number of scientists who work(ed) for NASA' FROM person WHERE DESCRIPTION LIKE '%NASA%' UNION "
		  . "SELECT 'NOAA', COUNT(*), 'Number of scientists who work(ed) for NOAA' FROM person WHERE DESCRIPTION LIKE '%NOAA%' UNION "
		  . "SELECT 'Nobel Laureates', COUNT(*), 'Number of Nobel prize recipients' FROM person WHERE DESCRIPTION LIKE '%Nobel%' AND DESCRIPTION NOT LIKE '%Akzo%' UNION "
		  . "SELECT 'Published', COUNT(*), 'Number of scientists who have published peer-reviewed science' FROM person WHERE PUBLISHED UNION "
		  . "SELECT 'Checked', COUNT(*), 'Number of scientists whose credentials have been checked' FROM person WHERE CHECKED;";
		$sql = [$countSql, $selectSql];
		return executeQuery($sql, null, true, $start, $count, $status);
	}

	/**
	 * Returns whether the script is running in CLI mode.
	 * @return bool true if running in CLI mode, otherwise false.
	 */
	function isCli() : bool {
		return php_sapi_name() === 'cli';
	}

	/**
	 * Reads environment variables from the file .env
	 * @throws Exception if the .env file is missing
	 */
	function readenv() {
		if (getenv(JWT_SECRET) == false) {
			$stream = fopen('./.env', 'r');
			if ($stream !== false) {
				while (($line = fgets($stream)) !== false) {
					if (strlen($line) == 0 || $line[0] == '#')
						continue;
					$setting = str_replace(["\r", "\n"], '', $line);
					putenv($setting);
				}
				fclose($stream);
			} else {
				throw new Exception('Environment file missing');
			}
		}
	}

	/**
	 * Fills an array with default values where the required keys are not present.
	 * @param $array The array to update, passed by reference.
	 * @param $defaults The defaults to apply.
	 */
	function setDefaults(&$array, $defaults) {
		foreach ($defaults as $key => $value)
			setDefault($array, $key, $value);
	}

	/**
	 * Sets a default value in an array, if the key is not present.
	 * @param $array The array to update, passed by reference.
	 * @param $key The key to check.
	 * @param $value The value to set in $array if $key is not already present.
	 */
	function setDefault(&$array, $key, $default) {
		if (!array_key_exists($key, $array)) {
			$array[$key] = $default;
		// This code converts numeric strings to ints, but I don't think it makes any difference.
// 		} else {
// 			$value = $array[$key];
// 			if (is_string($value) && is_numeric($value))
// 				$array[$key] = intval($value);
		}
	}

	/**
	 * Checks that the length of an array is within expected bounds.
	 * @param $array The array to check.
	 * @param $minCount The inclusive minimum array length.
	 * @param $maxCount The inclusive maximum array length.
	 * @return true if the length of $array is within bounds, otherwise false.
	 */
	function checkArray($array, $minCount, $maxCount) {
		$segCount = count($array);
		return $segCount >= $minCount && $segCount <= $maxCount;
	}

	/**
	 * Returns the request body as an anonymous object.
	 * @return object The request body.
	 */
	function getRequestBody() {
		$stream = isCli() ? 'php://stdin' : 'php://input';
		return json_decode(file_get_contents($stream), false);
	}

	/**
	 * Executes an SQL query.
	 * @param $sql The SQL query/queries to execute. Dual queries are passed as a two-element array.
	 * @param $params The parameters to bind to the query/queries; parameter count must match query markers.
	 * @param $multi true if the query could yield multiple rows.
	 * @param $start The index of the first result row to return.
	 * @param $start The maximum number of result rows to return.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object If $multi is false, returns the requested row as an object. If $multi is true, returns a
	 * ResultSet object containing the total count and the requested rows as an array of objects. Otherwise, returns
	 * a single row from the database (if found).
	 */
	function executeQuery(array|string $sql, $params, bool $multi, int $start, int $count, &$status) : object {
		$result = $multi ? new ResultSet() : null;

		// Validate & split $sql parameter.
		$isSqlArray = is_array($sql);
		if ($multi && (!$isSqlArray || !checkArray($sql, 2, 2))) {
			$status = StatusCode::INTERNAL_SERVER_ERROR;
			return $result;
		}
		$countSql = $isSqlArray ? $sql[0] : null;
		$querySql = $isSqlArray ? $sql[1] : $sql;

		$pdo = new PDO(getenv(DB_URL), getenv(DB_USER), getenv(DB_PASSWORD));

		// If it's a multi-row result set, we must first count the rows (since we may not be returning all of them).
		if ($multi && $countSql) {
			$stmt = $pdo->prepare($countSql);
			try {
				if ($stmt->execute($params)) {
					$result->count = $stmt->fetch(PDO::FETCH_NUM)[0];
				} else {
					$status = StatusCode::NOT_FOUND;
					return $result;
				}
			} finally {
				$stmt->closeCursor();
			}
		}

		// Build and execute the main query.
		$stmt = $pdo->prepare($querySql);
		try {
			if ($stmt->execute($params)) {
				for ($i = 0, $limit = $start + $count; $count == 0 || $i < $limit; $i++) {
					$row = $stmt->fetch(PDO::FETCH_OBJ);
					// Since we can't seem to get $cursorOrientation = PDO::FETCH_ORI_ABS to work,
					// just skip any unwanted initial rows.
					if ($i < $start)
						continue;
					if ($row) {
						if ($multi) {
							$result->records[] = $row;
						} else {
							$result = $row;
							break;
						}
					} else {
						break;
					}
				}
				$status = StatusCode::OK;
			} else {
				$status = StatusCode::NOT_FOUND;
			}
		} finally {
			$stmt->closeCursor();
		}
		return $result;
	}

	/**
	 * Executes an SQL update statement.
	 * @param $sql The SQL update (or delete) statement to execute.
	 * @param $params The parameters to bind to the prepared statement.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return bool true on success, false on failure.
	 */
	function executeUpdate($sql, $params, &$status) : bool {
		$pdo = new PDO(getenv(DB_URL), getenv(DB_USER), getenv(DB_PASSWORD));
		$stmt = $pdo->prepare($sql);
		$result = $stmt->execute($params);
		$stmt->closeCursor();
		return $result;
	}

	/**
	 * Emits CORS-related headers in response to a CORS pre-flight OPTIONS request.
	 * @param $options The methods to allow.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return null null, as an OPTIONS response does not include a body.
	 */
	function emitWriteOptions($options, &$status) {
		header('Access-Control-Allow-Methods: ' . $options);
		$status = StatusCode::OK;
		return null;
	}

	/**
	 * Verifies credentials passed by the client.
	 * @param $credentials The credentials (userId and password).
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return object|null The user record if successfully authenticated, otherwise null.
	 */
	function verifyCredentials(object $credentials, &$status) : object|null {
		$sql = 'SELECT * FROM user WHERE ID = ?';
		$user = executeQuery($sql, [$credentials->userId], false, 0, 0, $status);
		if ($user) {
			// NOTE: Use password_hash() to create the password hash stored in the database.
			if (!password_verify($credentials->password, $user->PASSWORD_HASH))
				$user = null;
		}
		if (!$user)
			$status = StatusCode::UNAUTHORIZED;
		return $user;
	}

	/**
	 * Generates a JWT token for an authenticated user.
	 * @param $user Details of an authenticated user.
	 * @return string The signed JWT bearer token.
	 */
	function generateJwt(object $user) : string {
		$jwtSecret = getenv(JWT_SECRET);
		$jwtTtl = intval(getenv(JWT_TTL));

		$header = [
			"alg" => "HS256", 
			"typ" => "JWT" 
		];
		$header = base64UrlEncode(json_encode($header));
		$issuedAt = time();
		$payload =  [
			"sub" => $user->ID,
			"fnm" => $user->FIRST_NAME,
			"lnm" => $user->LAST_NAME,
			"eml" => $user->EMAIL,
			// "iss" => "campaign-resources.org",
			"iat" => $issuedAt,
			"exp" => $issuedAt + $jwtTtl,
		];
		$payload = base64UrlEncode(json_encode($payload));
		$signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", $jwtSecret, true));
		$jwt = "$header.$payload.$signature";
		return $jwt;
	}

	/**
	 * Validates a JWT token.
	 * @param string $jwt The JSON web token to validate.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return bool true if the JWT token validated successfully and is still unexpired, otherwise false.
	 */
	function validateJwt(string $jwt, &$status) : bool {
		readenv();
		$secret = getenv(JWT_SECRET);
		if ($secret !== false) {
			$tokenParts = explode('.', $jwt);
			$base64UrlHeader = $tokenParts[0];
			$base64UrlPayload = $tokenParts[1];
			$base64UrlSignature = $tokenParts[2];

			// Check the expiration time - note this will cause an error if there is no 'exp' claim in the token
			$payloadStr = base64UrlDecode($base64UrlPayload);
			$payload = json_decode($payloadStr);
			$tokenUnexpired = time() < intval($payload->exp);
			if ($tokenUnexpired) {
				// Compute a signature based on the header and payload using the secret,
				// then verify that it matches the signature provided in the token.
				$signatureComp = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secret, true);
				$base64UrlSignatureComp = base64UrlEncode($signatureComp);
				return $base64UrlSignature === $base64UrlSignatureComp;
			}
		}
		$status = StatusCode::UNAUTHORIZED;
		return false;
	}

	/**
	 * Authorises a write request (i.e., update, insert or delete).
	 * @param $method The method being invoked.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return bool true if the request is authorised, false if denied.
	 */
	function authoriseWriteRequest($method, &$status) : bool {
		switch ($method) {
			case PUT:
			case POST:
			case PATCH:
			case DELETE:
				$headers = isCli() ? [AUTHORIZATION => null] : apache_request_headers();
				if (array_key_exists(AUTHORIZATION, $headers)) {
					$authString = trim($headers[AUTHORIZATION]);
					if (isset($authString) && $authString && stripos($authString, 'Bearer ') == 0) {
						$jwt = trim(substr($authString, 7));
						return validateJwt($jwt, $status);
					}
				}
				header('WWW-Authenticate: Bearer');
				$status = StatusCode::UNAUTHORIZED;
				return false;
			default:
				return true;
		}
	}

	/**
	 * Encodes a string using Base64 URL encoding.
	 * @param string $text The string to encode.
	 * @return string The encoded string.
	 * @see https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid/15875555#15875555
	 */
	function base64UrlEncode($text) : string {
		return base64UrlEscape(base64_encode($text));
	}

	/**
	 * Escapes a Base64 string to make it suitable for inclusion in a URI.
	 * @param string $text The Base64 string to escape.
	 * @return string The escaped string.
	 */
	function base64UrlEscape($text) : string {
		$str = str_replace('+', '-', $text);
		$str = str_replace('/', '_', $str);
		return str_replace('=', '', $str);
	}

	/**
	 * Unescapes an escaped string to convert it back into valid Base64 encoded form.
	 * @param string $text The escaped Base64 string to unescape.
	 * @return string The unescaped string.
	 */
	function base64UrlUnescape($text) : string {
		$str = $text . substr('===', (strlen($text) + 3) % 4);
		$str = str_replace('_', '/', $str);
		$str = str_replace('-', '+', $str);
		return $str;		
	}

	/**
	 * Decodes a Base64 URL encoded string.
	 * @param string $text The string to decode.
	 * @return string The decoded string.
	 */
	function base64UrlDecode($text) : string {
		return base64_decode(base64UrlUnescape($text));
	}
	
?>