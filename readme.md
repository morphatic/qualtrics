## Qualtrics API wrapper for Laravel 4

[![Latest Stable Version](https://poser.pugx.org/morphatic/qualtrics/v/stable.svg)](https://packagist.org/packages/morphatic/qualtrics) 
[![Total Downloads](https://poser.pugx.org/morphatic/qualtrics/downloads.svg)](https://packagist.org/packages/morphatic/qualtrics) 
[![Latest Unstable Version](https://poser.pugx.org/morphatic/qualtrics/v/unstable.svg)](https://packagist.org/packages/morphatic/qualtrics) 
[![Build Status](https://travis-ci.org/morphatic/qualtrics.png?branch=master)](https://travis-ci.org/morphatic/qualtrics) 
[![Coverage Status](https://coveralls.io/repos/morphatic/qualtrics/badge.png)](https://coveralls.io/r/morphatic/qualtrics)
[![Dependency Status](https://www.versioneye.com/user/projects/52c8b848ec1375078b0000d1/badge.png)](https://www.versioneye.com/user/projects/52c8b848ec1375078b0000d1)
[![License](https://poser.pugx.org/morphatic/qualtrics/license.svg)](https://packagist.org/packages/morphatic/qualtrics)

The [Qualtrics REST API](https://survey.qualtrics.com/WRAPI/ControlPanel/docs.php) allows you to query the [Qualtrics](http://www.qualtrics.com) system using a simple URL syntax. All requests are simple GET or POST requests that return XML or JSON. The REST API allows you to interact with any part of the Qualtrics system allowing for full integration with client systems.

This Qualtrics API wrapper for Laravel 4 provides access to the API via a PHP wrapper.

### Installation

To install the Qualtrics API wrapper for Laravel 4, add the following to the `"require"` element of your `composer.json` file:

```
    "morphatic/qualtrics": "1.1.*@dev"
```

Then run `composer update` from the command line.

In `config.app` under `providers` add:

```
    'Morphatic\Qualtrics\QualtricsServiceProvider',
```

Finally, from the command line run:

```
    php artisan config:publish morphatic/qualtrics
```

And in the `app/config/packages/morphatic/qualtrics/config.php` file update your username and API token.  Optionally you may also add a library ID to the config file.

### Basic usage

You can create a basic instance of the class with just a username and API token. Here's an example:

```php
// Qualtrics login email 
$user  = 'someuser@test.com';

// Qualtrics API Token
$token = 'RmvGK6vjF3Izx8Ea2pCisDDSpqE4dELw9AzheBDc';

// create the instance
$qtrx  = new Qualtrics( $user, $token );

// get the user info (no additional parameters necessary)
$info  = $qtrx->getUserInfo();

// accessible libraries
$libraries = $info->Libraries;

// get a particular survey (requires ID of desired survey)
$mysurvey = $qtrx->getSurvey( [ 'SurveyID' => 'SV_9EQYOts8KmOle04' ] );                       
```

Any additional parameters required by the call ([see the official API documentation](https://survey.qualtrics.com/WRAPI/ControlPanel/docs.php)) should be passed as an associative array of key/value pairs.  The keys are case sensitive and follow the naming convention of the official docs.

### Disclaimer

I am in no way, shape, or form affiliated with [Qualtrics](http://www.qualtrics.com).  In order to [gain access to the Qualtrics REST API](http://qualtrics.com/university/researchsuite/developer-tools/api-integration/qualtrics-rest-api/), your organization or institution must subscribe to this service.  In my case, it meant contacting an IT administrator at my university and asking them to do so.

### License

Qualtrics API wrapper for Laravel 4 is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)