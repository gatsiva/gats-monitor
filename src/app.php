<?php

/* GATSIVA CRYPTO MONITOR
 *
 *
 * VERSIONS:
 *
 *  0.1		- Initial prototype
 *  0.2		- Packaged version
 *  0.3		- Update to now utilize the Gatsiva Public API, added README file
 *
 */

// Load the composer auto-load deal
require __DIR__ . '/vendor/autoload.php';

// Create shortcuts to some of the classes we will use
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

// Set the default timezone
date_default_timezone_set('UTC');
$version = "0.3";

// Get the configuration inforamtion
$global_config = readConfiguration();

if ($global_config == null) {
	echo "Error reading configuration. Please check that " . $filename . " is formatted properly.\n\n";
	exit(1);
}

// Load the preferences we need from the global_config file
$run_once = $global_config['run_once'];
$log_debug = $global_config['log_debug'];
$email_always = $global_config['email_always'];
$email_on_errors = $global_config['email_on_errors'];
$api_service_url = $global_config['api_service_url'];
$symbols_to_watch = $global_config['symbols'];
$sleep_mins = $global_config['sleep_mins'];

// Create the logger
$logger = new Logger('gatsmonitor');
if ($global_config['log_type'] == 'file') {
	$log_filename = date("YmdHis") . "-" . $version . ".log";
	$logger->pushHandler(new StreamHandler(__DIR__ . '/' . $log_filename, Logger::DEBUG));
}
else {
	$logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
}

$first_run = true;

while ($first_run || !$run_once) {

	$time_start = time();

	// Make sure we run at least once
	if ($first_run) {
		$first_run = false;
	}

	// Setup operation flags
	$found_true = false;
	$found_error = false;

	log_info("Starting");

	$email_string = "<h1>GATS Monitor Function</h1>\n\n";
	$email_string .= "<table border='1'>";

	foreach ($symbols_to_watch as $symbol=>$items) {

		// Get the triggers and indicators for this symbol
		$triggers = $items['triggers'];
		$indicators = $items['indicators'];

		log_info("Checking " . $symbol);
		$email_string .= "<tr><td colspan='2'><h2>" . $symbol . "</h2></td></tr>";

		// Check the triggers
		$email_string .= "<tr><td colspan='2'><h3>Triggers</h3></td></tr>";
		$data = callTFTransaction($symbol,$triggers);
		if ($data != null) {

			$marketdate = date(DATE_RFC822, $data['last_timestamp']);
			$email_string .= "<tr><td colspan='2'><font size='-1'><b>Last Market Date: " . $marketdate . "</b></font></td></tr>";

			foreach($data['conditions'] as $result) {

				// If the result is true
				if ($result['value']) {
					$email_string .= "<tr style='color:green;background:#ccffcc'><td><b>" . $result['definition'] . "</b></td><td><b>True</b></td></tr>";

					// We found at least one true
					log_debug("Symbol: " . $symbol . "  Definition: " . $result['definition'] . " was true");
					$found_true = true;
				}
				// Otherwise if it is false
				else {
					$email_string .= "<tr><td>" . $result['definition'] . "</td><td>False</td></tr>";
				}
			}
		}
		else {
			$found_error = true;
			$email_string .= "<tr><td colspan='2'>Error response for " . $symbol . "</td></tr>";
		}

		// Then check the indicators
		$email_string .= "<tr><td colspan='2'><h3>Indicators</h3></td></tr>";
		$data = callIndicatorTransaction($symbol,$indicators);
		if ($data != null) {
			foreach($data['indicators'] as $result) {
				$email_string .= "<tr><td>" . $result['indicator'] . "</td><td>" . $result['value'] . "</td></tr>";
			}
		}
		else {
			$email_string .= "<tr><td colspan='2'>Indicator error response for " . $symbol . "</td></tr>";
		}
	}

	$email_string .= "</table>";


	// If the send email flag was true

	if (
		($found_error && $email_on_errors)  // If we found an error and we've been configured to email for errors
			|| 								//   - or -
		$email_always						// If we are configured to always email
			|| 								//   - or -
		$found_true) {						// If we found something true

		sendEmail($email_string);
	}
	else {
		log_info("Nothing to email");
	}

	// If we're not running once, then we need to sleep based on the time remaining
	if (!$run_once) {

		$runtime = time() - $time_start;
		$sleeptime = ($sleep_mins * 60) - $runtime;

		log_debug("Executed for " . $runtime . " seconds");
		log_info("Sleeping for " . $sleeptime . " remaining seconds");
		sleep($sleeptime);
	}
	else {
		log_info("Done executing");
	}
}



/* *********************************************************************************************

INTERNAL UTILITY FUNCTIONS

********************************************************************************************** */

/**
 *

Example of input

 {
   "symbol": "BTC:USD:daily",
   "at_timestamp": 1505721864,
   "conditions": [
     "bollinger range(20,2) crosses below 0",
     "sma(28) crosses sma(14)"
   ]
 }

Example of output

 {
   "symbol": "BTC:USD:daily",
   "last_timestamp": 1505721864,
   "conditions": [
     {
       "definition": "bollinger range(20,2) crosses below 0",
       "value": true
     },
     {
       "definition": "sma(28) crosses sma(14)",
       "value": false
     }
   ]
 }
*/
function callTFTransaction($symbol, $conditions) {

	global $global_config;

	// Create the URL to the service
	$url = $global_config['api_service_url'] . '/conditions/status';

	// Create the input array
	$input = array(
		'symbol' => $symbol,
		'conditions' => $conditions
	);

	// Make the request and return the data
	$data = callHttpPostRequest($url, $input);

	return $data;
}

/**
 *

Example of input

{
  "symbol": "BTC:USD:daily",
  "indicators": [
    "bollinger range(20,2)",
    "close(1)",
    "price change percentage(28)"
  ]
}


Example of output

{
  "symbol": "BTC:USD:daily",
  "last_timestamp": 1505721864,
  "indicators": [
    {
      "definition": "bollinger range(20,2)",
      "value": "0.8901"
    },
    {
      "definition": "close(1)",
      "value": "386.23"
    },
    {
      "definition": "price change percentage(28)",
      "value": "0.67342"
    }
  ]
}
 */
function callIndicatorTransaction($symbol, $indicators) {

	global $global_config;

	// Create the URL to the service
	$url = $global_config['api_service_url'] . '/indicators/status';

	// Create the input array
	$input = array(
		'symbol' => $symbol,
		'indicators' => $indicators
	);

	// Make the request and return the data
	$data = callHttpPostRequest($url, $input);

	return $data;
}

/**
 * Calls an HTTP get request
 */
function callHttpGetRequest($url) {

	try {
		$client = new HttpClient();
		$response = $client->get(
			$url,
			[
					'http_errors'=>false,
					'verify'=>true,
					'headers'=>['Accept' => 'application/json']
				]
		);

		return checkResponse($response);

	}
	catch (RequestException $e) {
		log_error("callHttpGetRequest: There was an error trying to retrieve " . $url . " with error message " . $e->getMessage());
		return null;
	}
}



/**
 * Calls an HTTP post request
 */
function callHttpPostRequest($url, $json_data) {
	try {
		$client = new HttpClient();
		$response = $client->post(
			$url,
			[
				'http_errors'=>false,
				'verify'=>true,
				'headers'=>['Accept' => 'application/json'],
				'json'=>$json_data
			]
		);

		return checkResponse($response);
	}
	catch (RequestException $e) {
		log_error("callHttpPostRequest: There was an error trying to retrieve " . $url . " with error message " . $e->getMessage());
		return null;
	}
}


/**
 * Checks the response object for various status codes and either returns the data or information on what is going on
 */
function checkResponse($response) {

	if ($response->getStatusCode() == 200) {
		return json_decode($response->getBody(), true);
	}
	else {
		log_error("callHttpGetRequest: Error response from public API: " . json_decode($response->getBody(), true)['message']);

		// Input error
		if ($response->getStatusCode() == 422) {
			log_error("callHttpGetRequest: Input Errors: " . print_r(json_decode($response->getBody(), true)['errors'],true));
		}

		return null;
	}
}

/**
 * Sends an email in a data array
 */
function sendEmail($string) {

	global $global_config;

	log_debug("Attempting to send email message");

	$mail = new PHPMailer;

	//$mail->SMTPDebug = 3;                               // Enable verbose debug output

	$mail->isSMTP();                                                  // Set mailer to use SMTP
	$mail->Host = $global_config['email']['from_smtp_host'];          // Specify main and backup SMTP servers
	$mail->SMTPAuth = true;                                           // Enable SMTP authentication
	$mail->Username = $global_config['email']['from_smtp_username'];  // SMTP username
	$mail->Password = $global_config['email']['from_smtp_password'];  // SMTP password
	$mail->SMTPSecure = $global_config['email']['from_smtp_type'];    // Enable TLS encryption, `ssl` also accepted
	$mail->Port = $global_config['email']['from_smtp_port'];          // TCP port to connect to

	$mail->setFrom($global_config['email']['from_address'], $global_config['email']['from_name']);
	$mail->addAddress($global_config['email']['to_address'], $global_config['email']['to_name']);

//	$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
//	$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
	$mail->isHTML(true);                                  // Set email format to HTML

	$mail->Subject = 'Gatsiva Monitoring Report - ' . date('D, d M Y H:i:s');
	$mail->Body    = $string;
//	$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

	if(!$mail->send()) {
		log_error('Message could not be sent.');
		log_error('Mailer Error: ' . $mail->ErrorInfo);
	} else {
		log_debug('Email message has been sent');
	}
}

/**
 * Reads the configuration and returns the data structure representing the JSON file
 */
function readConfiguration() {

	global $argv;

	// First check for the environment variable
	$filename = getenv('GATSIVA_CONFIG_FILE');

	// If we didn't get it, then try the command arguments instead
	if ($filename == null) {
		if ($argv[1] != null) {
			$filename = $argv[1];
		}
	}
	// If we did get it ..
	else {
		// And there was a command argument, then throw a warning
		if ($argv[1] != null) {
			echo "** Warning. Ignoring argument " . $argv[1] . " since GATSIVA_CONFIG_FILE environment variable was set. **\n";
		}
	}

	// If no file was passed in
	if ($filename == null) {
		echo "Error. Cound not find configuration file. Please either pass in a filename as an argument: php app.php <filename> or set the GATSIVA_CONFIG_FILE environment variable.\n";
		exit(1);
	}

	// If the file doesn't exist
	if(!file_exists($filename)) {
		echo "Error reading configuration. Cound not find " . $filename . ". Please ensure that your configuration file exists.\n";
		exit(1);
	}

	// Get the JSON decoded data structure from the contents of the file
	$global_config = json_decode(file_get_contents($filename), true);

	// If there was a parsing error, throw and error and quit
	if ($global_config == null) {
		echo "Error reading configuration. Please check that " . $filename . " is formatted properly.\n\n";
		exit(1);
	}

	return $global_config;
}


/* *********************************************************************************************

LOGGING UTILITY FUNCTIONS

********************************************************************************************** */

function log_debug($a) {
	global $logger, $log_debug;
	if ($log_debug) {
		$logger->addDebug($a);
	}
}

function log_info($a) {
	global $logger;
	$logger->addInfo($a);
}

function log_error($a) {
	global $logger;
	$logger->addError($a);
}

function log_warning($a) {
	global $logger;
	$logger->addWarning($a);
}

?>
