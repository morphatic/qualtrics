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
    "morphatic/qualtrics": "1.0.*@dev"
```

Then run `composer update` from the command line.

In `config.app` under `providers` add:

```
    'Morphatic\Qualtrics\QualtricsServiceProvider',
```

And under `aliases` add:

```
   'Qualtrics' => 'Morphatic\Qualtrics\Facades\Qualtrics',
```

Finally, from the command line run:

```
    php artisan config:publish morphatic/qualtrics
```

And in the `app/config/packages/morphatic/qualtrics/config.php` file update your username and API token.  Optionally you may also add a library ID to the config file.

### Basic usage



### Disclaimer

I am in no way, shape, or form affiliated with [Qualtrics](http://www.qualtrics.com).  In order to [gain access to the Qualtrics REST API](http://qualtrics.com/university/researchsuite/developer-tools/api-integration/qualtrics-rest-api/), your organization or institution must subscribe to this service.  In my case, it meant contacting an IT administrator at my university and asking them to do so.

### License

Qualtrics API wrapper for Laravel 4 is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)