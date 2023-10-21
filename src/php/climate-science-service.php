<?php
	/*
	* Climate Science Service: a PHP REST service for querying a climate science database.
	* Copyright © 2023 Adrian Price. All rights reserved.
	* Licensed under the GNU Affero General Public License v.3 https://www.gnu.org/licenses/agpl-3.0.html
	*/

	enum Client {
		case local;
		case remote;
	}
	const DELETE = 'DELETE';
	const GET = 'GET';
	const OPTIONS = 'OPTIONS';
	const PATCH = 'PATCH';
	const POST = 'POST';
	const PUT = 'PUT';
	const PARAM_START = 'start';
	const PARAM_COUNT = 'count';
	const PARAM_LAST_NAME = 'lastName';
	const PARAM_PERSON_ID = 'personId';
	const PARAM_PUBLICATION_ID = 'publicationId';
	const PARAM_DECLARATION_ID = 'declarationId';
	const PARAM_QUOTATION_ID = 'quotationId';
	// @formatter:off
	const FIND_DEFAULTS = [
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
	const PERSON_FIELDS = [
			'ID',
			'TITLE',
			'FIRST_NAME',
			'NICKNAME',
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
	const URL = 'mysql:host=DT-ADRIAN.local;dbname=climate';
	const USER = 'climate';
	const PASSWORD = 'climate';

	require './StatusCode.php';
	use PH7\JustHttp\StatusCode;

	/** Used to return multi-row results. */
	class ResultSet {
		public $count = 0;
		public $records = [];
	}
	class Count {
		public $COUNT = 0;
	}

	try {
		// When running as CLI, HTTP method and request URI are passed as command line arguments.
		if (php_sapi_name() === 'cli') {
			if ($argc != 3)
				throw new Error("Invalid argument(s)");

			$_SERVER['SERVER_ADDR'] = '192.168.0.110';
			$_SERVER['REMOTE_ADDR'] = '192.168.0.110';
			$_SERVER['REQUEST_METHOD'] = strtoupper($argv[1]);
			$_SERVER['REQUEST_URI'] = $argv[2];
		}
		// TODO: implement proper ATH & ATZ to constrain write calls.
		define('CLIENT', $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'] ? Client::local : Client::remote);
		preg_match('#^/climate-service/([^?]*)(?:\?(.*))?$#', $_SERVER['REQUEST_URI'], $matches);
		$method = $_SERVER['REQUEST_METHOD'];
		$path = explode('/', $matches[1]);
		if (isset($matches[2]))
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

		http_response_code($status);

		// If there is a result, emit it to the body
		if (isset($result))
			echo json_encode($result);
	} catch (Exception $e) {
		echo 'Error: ' . $e->getMessage();
		http_response_code(StatusCode::INTERNAL_SERVER_ERROR);
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
	 * Executes an SQL query.
	 * @param $sql The SQL query/queries to execute. Dual queries are passed as a two-element array.
	 * @param $params The parameters to bind to the query/queries; parameter count must match query markers.
	 * @param $multi true if the query could yield multiple rows.
	 * @param $start The index of the first result row to return.
	 * @param $start The maximum number of result rows to return.
	 * @param $status The HTTP status code to return, passed by reference.
	 * @return ResultSet|Count|object If $multi is false, returns the requested row as an object. If $multi is true, returns a
	 * ResultSet object containing the total count and the requested rows as an array of objects.
	 */
	function executeQuery(array|string $sql, $params, bool $multi, int $start, int $count, &$status) {
		$result = $multi ? new ResultSet() : null;

		// Validate & split $sql parameter.
		$isSqlArray = is_array($sql);
		if ($multi && (!$isSqlArray || !checkArray($sql, 2, 2))) {
			$status = StatusCode::INTERNAL_SERVER_ERROR;
			return $result;
		}
		$countSql = $isSqlArray ? $sql[0] : null;
		$querySql = $isSqlArray ? $sql[1] : $sql;

		$pdo = new PDO(URL, USER, PASSWORD);

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
	 * Generates two versions of a given SQL statement from a template.
	 * @param string $template The SQL template, with '%FIELDS%' placeholder(s).
	 * @param string $fields The fields insert into the second SQL statement.
	 * @return array The first element will be the COUNT(*) SQL and the second the actual SQL.
	 * @unused
	 */
	function splitSql(string $template, string $fields) : array {
		$array[0] = str_replace('%FIELDS%', 'COUNT(*)', $template);
		$array[1] = str_replace('%FIELDS%', $fields, $template);
		return $array;
	}

	/**
	 * Executes an SQL update statement.
	 * @param $sql The SQL update (or delete) statement to execute.
	 * @param $params The parameters to bind to the prepared statement.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return bool true on success, false on failure.
	 */
	function executeUpdate($sql, $params, &$status) {
		$pdo = new PDO(URL, USER, PASSWORD);
		$stmt = $pdo->prepare($sql);
		$result = $stmt->execute($params);
		$stmt->closeCursor();
		return $result;
	}

	/**
	 * Dispatches a REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 1, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		switch ($path[0]) {
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
			default :
				break;
		}
		return $result;
	}

	/**
	 * Dispatches a Person-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchPersonRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /person/{personId} => getPersonById($personId)
		# GET /person/find?start=0&count=10 => findPersons($start, $count)
		# GET /person/findByPublication?publicationId=0 => findPersonsByPublication($publicationId)
		# GET /person/findByDeclaration?declarationId=0 => findPersonsByDeclaration($declarationId)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersons($params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByPublication':
						setDefault($params, PARAM_PUBLICATION_ID, null);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersonsByPublication($params[PARAM_PUBLICATION_ID], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByDeclaration':
						setDefault($params, PARAM_PUBLICATION_ID, null);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPersonsByDeclaration($params[PARAM_DECLARATION_ID], $params[PARAM_START], $params[PARAM_COUNT], $status);
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
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchPublicationRequest($method, $path, $params, &$status) {
		if (!checkArray($path, 2, 2)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# GET /publication/{publicationId} => getPublicationById($publicationId)
		# GET /publication/find?start=0&count=10 => findPublications($start, $count)
		# GET /publication/findByAuthor?personId=0&lastName=author&start=0&count=10 => findPublicationsByAuthor($personId, $personLastName, $start, $count)
		switch ($method) {
			case GET:
				switch ($path[1]) {
					case 'find':
						setDefaults($params, FIND_DEFAULTS);
						$result = findPublications($params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByAuthor':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findPublicationsByAuthor($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default :
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
	 * @param $status The HTTP status code to send, passed by reference.
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
						$result = findDeclarations($params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findBySignatory':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findDeclarationsBySignatory($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_START], $params[PARAM_COUNT], $status);
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
	 * @param $status The HTTP status code to send, passed by reference.
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
						$result = findQuotations($params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					case 'findByAuthor':
						setDefaults($params, USER_DEFAULTS);
						setDefaults($params, FIND_DEFAULTS);
						$result = findQuotationsByAuthor($params[PARAM_PERSON_ID], $params[PARAM_LAST_NAME], $params[PARAM_START], $params[PARAM_COUNT], $status);
						break;
					default :
						$result = getQuotationById($path[1], $status);
						break;
				}
				break;
			case PATCH:
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
	 * Authorises a write (i.e., update or delete) request.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return bool true if the request is authorised, false if denied.
	 */
	function authoriseWriteRequest(&$status) {
		// TODO: implement a proper ATH/ATZ scheme.
// 		if (CLIENT == Client::remote) {
// 			$status = StatusCode::FORBIDDEN;
// 			return false;
// 		}
		return true;
	}

	/**
	 * Emits CORS-related headers in response to a CORS pre-flight OPTIONS request.
	 * @param $options The methods to allow.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return null null, as an OPTIONS response does not include a body.
	 */
	function emitWriteOptions($options, &$status) {
		header('Access-Control-Allow-Methods: ' . $options);
		header('Access-Control-Allow-Headers: *');
		$status = StatusCode::OK;
		return null;
	}

	/**
	 * Dispatches an Authorship-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchAuthorshipRequest($method, $path, $params, &$status) {
		// Disallow authorship updates from remote clients.
		if (!authoriseWriteRequest($status))
			return null;

		if (!checkArray($path, 3, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# POST /authorship/{personId}/{publicationId} => createAuthorship
		# DELETE /authorship/{personId}/{publicationId} => deleteAuthorship
		setDefaults($params, AUTHORSHIP_DEFAULTS);
		switch ($method) {
			case POST:
				$result = createAuthorship($path[1], $path[2], $status);
				break;
			case DELETE:
				$result = deleteAuthorship($path[1], $path[2], $status);
				break;
			case OPTIONS:
				$result = emitWriteOptions('POST, DELETE, OPTIONS', $status);
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Dispatches an Signatory-related REST request.
	 * @param $method The HTTP method being invoked.
	 * @param $path The HTTP request URI path.
	 * @param $params The HTTP request parameters (from the query string).
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return ResultSet|object|null The result to return in the response body.
	 */
	function dispatchSignatoryRequest($method, $path, $params, &$status) {
		// Disallow signatory updates from remote clients.
		if (!authoriseWriteRequest($status))
			return null;

		if (!checkArray($path, 3, 3)) {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}

		# POST /signatory/{personId}/{declarationId} => createSignatory
		# DELETE /signatory/{personId}/{declarationId} => deleteSignatory
		setDefaults($params, SIGNATORY_DEFAULTS);
		switch ($method) {
			case POST:
				$result = createSignatory($path[1], $path[2], $status);
				break;
			case DELETE:
				$result = deleteSignatory($path[1], $path[2], $status);
				break;
			case OPTIONS:
				$result = emitWriteOptions('POST, DELETE, OPTIONS', $status);
				break;
			default:
				$status = StatusCode::METHOD_NOT_ALLOWED;
		}
		return $result;
	}

	/**
	 * Fetches a specified Person.
	 * @param $personId The ID of the Person to retrieve.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return object|null The requested Person.
	 */
	function getPersonById($personId, &$status) {
		return executeQuery("SELECT * FROM person WHERE ID=?", [$personId], false, 0, 1, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons.
	 * @param $start The index of the first Person to return.
	 * @param $count The maximum number of Persons to return.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersons($start, $count, &$status) {
		$sql = [
			"SELECT COUNT(*) FROM person",
			"SELECT * FROM person ORDER BY LAST_NAME, FIRST_NAME"
		];
		return executeQuery($sql, null, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons who are authors of a specified Publication.
	 * @param $publicationId The ID of the Publication whose authors are required.
	 * @param $start The index of the first Person to retrieve.
	 * @param $count The maximum number of Persons to retrieve.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersonsByPublication($publicationId, $start, $count, &$status) {
		$sql = [
			"SELECT COUNT(*) FROM person JOIN authorship ON authorship.PERSON_ID = person.ID WHERE authorship.PUBLICATION_ID=?",
			"SELECT * FROM person JOIN authorship ON authorship.PERSON_ID = person.ID WHERE authorship.PUBLICATION_ID=? ORDER BY person.LAST_NAME, person.FIRST_NAME"
		];
		return executeQuery($sql, [$publicationId], true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Persons who are signatories to a specified Declaration.
	 * @param $declarationId The ID of the Declaration whose signatories are required.
	 * @param $start The index of the first Person to retrieve.
	 * @param $count The maximum number of Persons to retrieve.
	 * @return ResultSet A ResultSet containing the requested Persons.
	 */
	function findPersonsByDeclaration($declarationId, $start, $count, &$status) {
		$sql = [
			"SELECT COUNT(*) FROM person JOIN signatory ON signatory.PERSON_ID = person.ID WHERE signatory.DECLARATION_ID=?",
			"SELECT * FROM person JOIN signatory ON signatory.PERSON_ID = person.ID WHERE signatory.DECLARATION_ID=? ORDER BY person.LAST_NAME, person.FIRST_NAME"
		];
		return executeQuery($sql, [$declarationId], true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Publication.
	 * @param $publicationId The ID of the Publication to retrieve.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return object|null The requested Publication.
	 */
	function getPublicationById($publicationId, &$status) {
		return executeQuery("SELECT * FROM publication WHERE ID=?", [$publicationId], false, 0, 1, $status);
	}

	/**
	 * Fetches a paginated sub-list of Publications.
	 * @param $start The index of the first Publication to return.
	 * @param $count The maximum number of Publications to return.
	 * @return ResultSet A ResultSet containing the requested Publications.
	 */
	function findPublications($start, $count, &$status) {
		$sql = [
			"SELECT COUNT(*) FROM publication",
			"SELECT * FROM publication ORDER BY PUBLICATION_YEAR, PUBLICATION_DATE"
		];
		return executeQuery($sql, null, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Publications authored by a specified Person.
	 * @param $personId The ID of the Person whose Publications are required.
	 * @param $start The index of the first Publication to retrieve.
	 * @param $count The maximum number of Publications to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Publications.
	 */
	function findPublicationsByAuthor($personId, $lastName, $start, $count, &$status) {
		$gotPersonId = isset($personId);
		$gotLastName = isset($lastName);
		if ($gotPersonId && $gotLastName) {
			$fields = implode(', ', PUBLICATION_FIELDS);
			# @formatter:off
			$sql = [
				  'SELECT (SELECT COUNT(*)'
				. ' FROM publication'
				. ' JOIN authorship ON authorship.PUBLICATION_ID = publication.ID'
				. ' WHERE authorship.PERSON_ID=? '
				. ')'
				. ' + (SELECT COUNT(*)'
				. ' FROM publication'
				. ' WHERE AUTHORS LIKE ? AND NOT EXISTS'
				. '  (SELECT 0 FROM authorship WHERE authorship.PUBLICATION_ID = publication.ID AND authorship.PERSON_ID = ?))',

				  'SELECT ' . $fields . ', TRUE AS LINKED'
				. ' FROM publication'
				. ' JOIN authorship ON authorship.PUBLICATION_ID = publication.ID'
				. ' WHERE authorship.PERSON_ID=? '
				. 'UNION '
				. 'SELECT ' . $fields . ', FALSE AS LINKED'
				. ' FROM publication'
				. ' WHERE AUTHORS LIKE ? AND NOT EXISTS'
				. '  (SELECT 0 FROM authorship WHERE authorship.PUBLICATION_ID = publication.ID AND authorship.PERSON_ID = ?) '
				. 'ORDER BY PUBLICATION_YEAR, PUBLICATION_DATE'
			];
			# @formatter:on
		} else {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		return executeQuery($sql, [
				$personId,
				'%' . $lastName . '%',
				$personId
		], true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Declaration.
	 * @param $declarationId The ID of the Declaration to retrieve.
	 * @param $status The HTTP status code to send, passed by reference.
	 * @return object|null The requested Declaration.
	 */
	function getDeclarationById($declarationId, &$status) {
		return executeQuery("SELECT * FROM declaration WHERE ID=?", [$declarationId], false, 0, 1, $status);
	}
	
	/**
	 * Fetches a paginated sub-list of Declarations.
	 * @param $start The index of the first Declaration to return.
	 * @param $count The maximum number of Declarations to return.
	 * @return ResultSet A ResultSet containing the requested Declarations.
	 */
	function findDeclarations($start, $count, &$status) {
		$sql = [
			"SELECT COUNT(*) FROM declaration",
			"SELECT * FROM declaration ORDER BY DATE DESC"
		];
		return executeQuery($sql, null, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Declarations signed by a specified Person.
	 * @param $personId The ID of the Person whose Declarations are required.
	 * @param $lastName The specified Person's last name.
	 * @param $start The index of the first Declaration to retrieve.
	 * @param $count The maximum number of Declarations to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Declarations.
	 */
	function findDeclarationsBySignatory($personId, $lastName, $start, $count, &$status) {
		$gotPersonId = isset($personId);
		$gotLastName = isset($lastName);
		if ($gotPersonId && $gotLastName) {
			$fields = implode(', ', DECLARATION_FIELDS);
			# @formatter:off
			// TODO: finalise the COUNT sql.
			$sql = [
				  'SELECT (SELECT COUNT(*)'
				. ' FROM declaration'
				. ' JOIN signatory ON signatory.DECLARATION_ID = declaration.ID'
				. ' WHERE signatory.PERSON_ID=? '
				. ')'
				. ' + (SELECT COUNT(*)'
				. ' FROM declaration'
				. ' WHERE SIGNATORIES LIKE ? AND NOT EXISTS'
				. '  (SELECT 0 FROM signatory WHERE signatory.DECLARATION_ID = declaration.ID AND signatory.PERSON_ID = ?))',

				  'SELECT ' . $fields . ', TRUE AS LINKED'
				. ' FROM declaration'
				. ' JOIN signatory ON signatory.DECLARATION_ID = declaration.ID'
				. ' WHERE signatory.PERSON_ID=? '
				. 'UNION '
				. 'SELECT ' . $fields . ', FALSE AS LINKED'
				. ' FROM declaration'
				. ' WHERE SIGNATORIES LIKE ? AND NOT EXISTS'
				. '  (SELECT 0 FROM signatory WHERE signatory.DECLARATION_ID = declaration.ID AND signatory.PERSON_ID = ?) '
				. 'ORDER BY DATE DESC'
			];
			# @formatter:on
		} else {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		return executeQuery($sql, [
				$personId,
				'%' . $lastName . '%',
				$personId
		], true, $start, $count, $status);
	}

	/**
	 * Fetches a specified Quotation.
	 * @param $quotationId The ID of the Quotation to retrieve.
	 * @param $status The HTTP status code to send, passed by reference.
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
		$status = $result ? StatusCode::NO_CONTENT : StatusCode::NOT_FOUND;
		return null;
	}

	/**
	 * Fetches a paginated sub-list of Quotations.
	 * @param $start The index of the first Quotation to return.
	 * @param $count The maximum number of Quotations to return.
	 * @return ResultSet A ResultSet containing the requested Quotations.
	 */
	function findQuotations($start, $count, &$status) {
		$sql = [
				"SELECT COUNT(*) FROM quotation",
				"SELECT * FROM quotation ORDER BY DATE DESC"
		];
		return executeQuery($sql, null, true, $start, $count, $status);
	}

	/**
	 * Fetches a paginated sub-list of Quotations authored by a specified Person.
	 * @param $personId The ID of the Person whose Quotations are required.
	 * @param $start The index of the first Quotation to retrieve.
	 * @param $count The maximum number of Quotations to retrieve.
	 * @return ResultSet|null A ResultSet containing the requested Quotations.
	 */
	function findQuotationsByAuthor($personId, $lastName, $start, $count, &$status) {
		$gotPersonId = isset($personId);
		$gotLastName = isset($lastName);
		if ($gotPersonId && $gotLastName) {
			$fields = implode(', ', QUOTATION_FIELDS);
			# @formatter:off
			$sql = [
					'SELECT COUNT(*)'
					. ' FROM quotation'
					. ' WHERE PERSON_ID=? '
					. ' OR '
					. ' AUTHOR LIKE ? AND PERSON_ID IS NULL',

					  'SELECT ' . $fields . ', TRUE AS LINKED'
					. ' FROM quotation'
					. ' WHERE PERSON_ID=? '
					. 'UNION '
					. 'SELECT ' . $fields . ', FALSE AS LINKED'
					. ' FROM quotation'
					. ' WHERE AUTHOR LIKE ? AND PERSON_ID IS NULL '
					. 'ORDER BY DATE DESC'
			];
			# @formatter:on
		} else {
			$status = StatusCode::BAD_REQUEST;
			return null;
		}
		return executeQuery($sql, [
				$personId,
				'%' . $lastName . '%'
		], true, $start, $count, $status);
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
			$status = StatusCode::NO_CONTENT;
		} else {
			 // Following code not required, as the create operation is idempotent.
// 			$status = StatusCode::CONFLICT;
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
			// Following code not required, as the delete operation is idempotent.
// 		} else {
// 			$status = StatusCode::NOT_FOUND;
		}
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
			$status = StatusCode::NO_CONTENT;
			// Following code not required, as the create operation is idempotent.
 		} else {
			$result = null;
// 			$status = StatusCode::CONFLICT;
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
			// Following code not required, as the delete operation is idempotent.
// 		} else {
// 			$status = StatusCode::NOT_FOUND;
		}
		return null;
	}
?>