<?php
require_once __DIR__."/core.php";

/// @defgroup JSON
/// @brief JSON emitting module (typically for making APIs) 
/// @ingroup Core

/// @addtogroup JSON
/// @{

/// Returns a response. It is empty. You should fill it with things.
/// 
/// 	$MyResponse = json_NewResponse();
/// 	$MyResponse['thing'] = "This is defifinitely a thing";
/// 	$MyResponse['other-thing'] = "Also a thing";
/// 
/// @param Integer $code (optional) Set an HTTP response code
/// All codes are supported, but the following are recommended:
/// * `200` (OK)
/// * `201` (Created)
/// * `202` (Accepted)
/// @retval Array (empty)
function json_NewResponse( $code = null ) {
	if ( is_numeric($code) ) {
		http_response_code($code);
	}
	return [];
}

/// Returns an error response.
/// 
/// **NOTE:** This function creates responses. 
/// If you are looking to emit errors, see #json_EmitError and #json_EmitServerError
/// 
/// @param Integer $code HTTP response code (default: 400 Bad Request)
/// @param String $msg (optional) Message
/// @param Multi $data (optional) Any data you wish to attach to the response
/// @retval Array
function json_NewErrorResponse( $code = 400, $msg = null, $data = null ) {
	// Set the error code in the response header //
	$response = json_NewResponse($code);

	$response['status'] = $code;
	$response['response'] = core_GetHTTPResponseText($code);
	
	// Return the response //
	if ( is_string($msg) ) {
		$response['message'] = $msg;
	}
	if ( !is_null($data) ) {
		$response['data'] = $data;
	}
	
	return $response;
}


/// Confirms that a string is a valid name for a JSON-P callback.
/// 
/// http://stackoverflow.com/questions/3128062/is-this-safe-for-providing-jsonp
/// 
/// @param String $name Callback name
/// @retval Boolean
function json_IsValidJSONPCallback($name) {
     $identifier_syntax
       = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

     $reserved_words = ['break', 'do', 'instanceof', 'typeof', 'case',
       'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
       'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
       'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
       'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
       'private', 'public', 'yield', 'interface', 'package', 'protected', 
       'static', 'null', 'true', 'false'];

     return preg_match($identifier_syntax, $name)
         && !in_array(mb_strtolower($name, 'UTF-8'), $reserved_words);
}

/// Emit an error document and **Exit**. Use this when the user makes a mistake.
/// 
/// @param Integer $code HTTP response code (default: 400).
/// All codes are supported, but the following are recommended:
/// * `400` (Bad Request)
/// * `401` (Unauthorized)
/// * `404` (Not Found)
/// @param String $msg (optional) Message
function json_EmitError( $code = 400, $msg = null, $data = null ) {
	json_Emit(json_newErrorResponse($code,$msg,$data));
}
function json_EmitFatalError( $code = 400, $msg = null, $data = null ) {
	json_Emit(json_newErrorResponse($code,$msg,$data));
	exit;
}


/// Emit an "Server Error" document and **Exit**. Use this when the server fails.
/// 
/// @param String $msg (optional) Message
function json_EmitServerError( $msg = null ) {
	json_Emit(json_newErrorResponse(500,$msg));
	exit;
}


/// Emit a JSON document.
/// 
/// Typically this is the last command in your document, but it doesn't have to be.
/// 
/// @param Array $out Response to output
/// @param Boolean $allow_jsonp Should JSON-P Callbacks be allowed? (Default: true)
function json_Emit( $out, $allow_jsonp = true ) {
	$prefix = "";
	$suffix = "";

	// By default, PHP will make '/' slashes in to '\/'. These flags fix that //
	$out_format = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	
	// If 'pretty' mode (i.e. readable) //
	if ( isset($_GET['pretty']) ) {
		$out_format |= JSON_PRETTY_PRINT;
	}
	
	// JSON-P //
	if ( isset($_GET['callback']) ) {
		if ( $allow_jsonp ) {
			$callback = $_GET['callback'];
			if ( json_IsValidJSONPCallback($callback) ) {
				$prefix = $callback . "(";
				$suffix = ");";
			}
			else {
				$out = json_NewErrorResponse(400,"Invalid JSON-P Callback");
			}
		}
		else {
			$out = json_NewErrorResponse(401,"JSON-P Unavailable");
		}
	}
		
	// Debug Info //
	if ( defined('SH_PHP_DEBUG') && isset($_GET['debug']) ) {
		$out['debug'] = [];
		
		if ( isset($GLOBALS['_CORE_SCRIPT_TIMER']) ) {
			$out['debug']['execute_time'] = core_MicrotimeToString($GLOBALS['_CORE_SCRIPT_TIMER']);
		}
		if (function_exists('db_GetQueryCount')) {
			$out['debug']['db_queries'] = db_GetQueryCount();
		}
		if (function_exists('cache_GetReads')) {
			$out['debug']['cache_reads'] = cache_GetReads();
			$out['debug']['cache_writes'] = cache_GetWrites();
		}
		if (function_exists('opcache_is_script_cached')) {
			// Technically, this is checking if "json.php" isn't cached //
			if (!opcache_is_script_cached(__FILE__)) {
				$out['debug']['opcache'] = "disabled";
			}
		}
		else {
			$out['debug']['opcache'] = "unavailable";
		}
		
		if ( isset($_SERVER['PATH_INFO']) ) {
			$out['debug']['url'] = $_SERVER['PATH_INFO'];
		}
		if ( getenv('REDIRECT_URL') ) {
			$out['debug']['redirect_url'] = getenv('REDIRECT_URL');
		}
		if ( getenv('REDIRECT_QUERY_STRING') ) {
			$out['debug']['redirect_query'] = getenv('REDIRECT_QUERY_STRING');
		}
	}
	
	// Output the Page //
	header('Content-Type: application/json');
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	//header("Pragma: no-cache"); // HTTP 1.0.
	//header("Expires: 0"); // Proxies.
	echo $prefix,str_replace('</', '<\/', json_encode($out,$out_format)),$suffix;
}

/// @}
