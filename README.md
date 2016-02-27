monolog-wordpress
=============



WordPress Handler for Monolog, which allows to store log messages in a MySQL Table.
It can log text messages to a specific table, and creates the table automatically if it does not exist.
The class further allows to dynamically add extra attributes, which are stored in a separate database field, and can be used for later analyzing and sorting.

Original based on:
Homepage: http://www.d-herrmann.de/projects/monolog-mysql-handler/

# Disclaimer
This is a very simple handler for monolog. It has a critical design flaw, which is it runs dbDelta on the log table everytime it is initialized. This allows the log to be very dynamic, but the trade off is a performance hit. dbDelta performs potentially dozens of SQL commands to compare the existing table structure to the proposed version. Depending on MySQL settings this may even slow down the site if tables are locked when trying to be written into. For my personal needs, I wanted to have a quick way to begin using Monolog, with plans to transistion to Mongo DB or cloud later, instead of using WordPress MySQL wrapper. This version works for custom plugin development, but I would not advise to distrubte this code in a public repository for general use on high traffic sites. You have been warned.

# Installation
monolog-wordpress is available via composer. Just add the following line to your required section in composer.json and do a `php composer.phar update`.

```
"bradmkjr/monolog-wordpress": ">1.0.0"
```

# Usage
Just use it as any other Monolog Handler, push it to the stack of your Monolog Logger instance. The Handler however needs some parameters:

- **$wpdb** Global instance of your DB connection.
- **$table** The table name where the logs should be stored
- **$additionalFields** simple array of additional database fields, which should be stored in the database. The columns are created automatically, and the fields can later be used in the extra context section of a record. See examples below. _Defaults to an empty array()_
- **$level** can be any of the standard Monolog logging levels. Use Monologs statically defined contexts. _Defaults to Logger::DEBUG_
- **$bubble** _Defaults to true_

# Examples
Given that $wpdb is your database instance, you could use the class as follows:

```php
//Import class
use WordPressHandler\WordPressHandler;

//ensure access to global $wpdb

global $wpdb;

//Create WordPressHandler
$wordPressHandler = new WordPressHandler($wpdb, "log", array('username', 'userid'), \Monolog\Logger::DEBUG);

$context = 'channel-name';

//Create logger
$logger = new \Monolog\Logger($context);
$logger->pushHandler($wordPressHandler);

//Now you can use the logger, and further attach additional information
$logger->addWarning("This is a great message, woohoo!", array('username'  => 'John Doe', 'userid'  => 245));
```

Example to use in your custom WordPress Plugin

```php
add_action( 'plugins_loaded', 'demo_function' );

function demo_function(){

require __DIR__ . '/vendor/autoload.php';

//Import class
use WordPressHandler\WordPressHandler;

//ensure access to global $wpdb
global $wpdb;

//Create WordPressHandler
$wordPressHandler = new WordPressHandler($wpdb, "log", array('app', 'version'), \Monolog\Logger::DEBUG);

$context = 'test-plugin-logging';

//Create logger
$logger = new \Monolog\Logger($context);
$logger->pushHandler($wordPressHandler);

//Now you can use the logger, and further attach additional information
$logger->addWarning("This is a great message, woohoo!", array('app'  => 'Test Plugin', 'version'  => '2.4.5'));

}
```

# License
This tool is free software and is distributed under the MIT license. Please have a look at the LICENSE file for further information.
