# Gatsiva API Command Line Monitor

This repository contains the project files and instructions for a monitoring tool that utilizes the [Gatsiva API][api] to keep track of crypto currency price movements. The monitoring tool is run via a command line interface and is configured using a json file.

Monitoring occurs by simply looking for a set of trigger conditions to be true, and if any one of them is true, the system will send an email with additional information (indicators). Both trigger conditions and indicators are completely configurable and are defined using the [Gatsiva Language][language]

API access is **currently limited to beta testers and collaborators** and utilizing this API will require the use of a valid API key. For more information on how to utilize the Gatsiva API or to request access, please visit the [Gatsiva Website](https://gatsiva.com).

## How to Install / Use

This application can be run either via directly in PHP or as a prepackaged Docker image.

If you are interested in learning more or contributing, please sign up to our [Gatsiva Community](https://discourse.gatsiva.com).

### Running via Docker

Running this application with [Docker](https://docker.com) is by far the easiest way to run this system.

Simply download and run the image, replacing `/path/to/your/config.json` with the path to your configuration file.

```
docker pull gatsiva/gats-monitor:latest
docker run --rm -v /path/to/your/config.json:/var/gatsiva/cli-config.json gatsiva/gats-monitor:latest
```

### Running via PHP

To run this via PHP, you need to download the repository, install the dependencies, and run the app.php with reference to the configuration file either via an argument or by setting the `GATSIVA_CONFIG_FILE` environment variable.

In the examples below, be sure to replace `/path/to/your/config.json` with your own configuration file.

**With Git, Docker, and PHP installed on your system**

If you have Docker installed on your system, you can avoid installing Composer to download the PHP library dependencies.

```
git clone git@github.com:gatsiva/gats-monitor.git
docker run --rm -v $(pwd)/gats-monitor/src:/app composer/composer install
php gats-monitor/src/app.php /path/to/your/config.json
```

**With Git, Composer, and PHP installed on your system**

If you have Composer already installed on your system, you can do it the old fashioned way

```
git clone git@github.com:gatsiva/gats-monitor.git
cd gats-monitor/src
composer install
php app.php /path/to/your/config.json
```

### Setting up For Periodic Monitoring

To set up for periodic monitoring, or set-and-forget, you can take a few different approaches.

1. Configure the application to run continuously and sleep a certain amount of time before re-executing
2. Execute the command periodically via cron or some other job scheduler


## Configuration

System behavior is configured via a single JSON file. In this file you configure how the command line monitor should behave.

To configure monitoring, create an array of symbols in the configuration file defined below, and for each symbol provide the system with a list of triggers and conditions. For each symbol, if any of the trigger conditions are true, the system will send an email with both the triggers and the values of the resultant indicators to `to_address` also specified in the configuration file.

For more detailed information on valid symbols, trigger conditions, or indicators, please see the [Gatsiva Public API][api] and [Gatsiva Language][language] reference documentation.

### Sample Configuration File

A sample configuration file is located in this repository at `config/sample-config.json`. Use this file as a template to create your own configuration file.

```
{
  "run_once": false,
  "sleep_mins": 60,
  "log_type": "console",
  "email_always" : false,
  "email_on_errors" : true,
  "log_debug" : true,
  "api_service_url" : "https://api.gatsiva.com/api/v1",
  "api_service_key" : "<insert your key here>",
  "email": {
    "to_name": "To person",
    "to_address": "address@server.com",
    "from_name": "Gatsiva Monitor",
    "from_address": "from@server.com",
    "from_smtp_host": "smtp.server.com",
    "from_smtp_port": "587",
    "from_smtp_type": "tls",
    "from_smtp_username": "from@server.com",
    "from_smtp_password": "password"
  },
  "symbols": {
    "BTC:USD:daily": {
      "triggers": [
        "sma(14) crosses over sma(28)",
        "bollinger range(14,2) < 0.1",
        "bollinger range(14,2) > 0.9"
      ],
      "indicators": [
        "close(1)",
        "price change percentage(1)",
        "price change percentage(7)",
        "bollinger range(14,2)",
        "sma(14)",
        "sma(28)"
      ]
    },
    "ETH:USD:daily": {
      "triggers": [
        "price change percentage(1) > 0.05",
        "bollinger range(24,2) < 0.1",
        "bollinger range(24,2) > 0.9"
      ],
      "indicators": [
        "close(1)",
        "price change percentage(1)",
        "price change percentage(24)"
      ]
    }
  }
}
```

### Configuration File Definition

The following attributes should be present in the configuration file.

| Value | Format | Allowable Values |
| :---- | :----- | :--------------- |
| `run_once` | Boolean | Determines if the script should run once only (true) or if it should continue to wait and poll the public API periodically |
| `sleep_mins` | Integer | The number of minutes to wait between queries if `run_once` is set to `true` |
| `log_type` | String | The type of logging to do. If set to `console` then the system will log to the screen, if set to `file` then the system will create a log file in the directory where the code is stored. Be sure to use `console` if you are using the Docker method. |
| `email_always` | Boolean | If set to true, each run of the system will always send an email regardless if any true results are found |
| `email_on_errors` | Boolean | If set to true, the system will always send an email if errors occur while running the system |
| `log_debug` | Boolean | If set to true, the system will log more messages for review |
| `api_service_url` | String | The URL of the Gatsiva API service |
| `api_service_key` | String | The API key utilized to access the service (currently not used) |
| `email` | Array | This section contains the email configuration for accounts to send from and send to |
| `symbols` | Array | This section contains the symbols to watch. Each symbol should contain a `triggers` array and an `indicators` array. |

## Contributing

For information on how to contribute to this project, please see the [Contribution Guide](CONTRIBUTING.md).

[language]: https://discourse.gatsiva.com/c/documentation/gatsiva-language
[api]: https://discourse.gatsiva.com/c/documentation/gatsiva-api
