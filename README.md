monolog-wordpress
=============

WordPress Handler for Monolog, which allows to store log messages in a MySQL Table.
It can log text messages to a specific table, and create the table automatically if it does not exist.
The class further allows to dynamically add extra attributes, which are stored in a separate database field, and can be used for later analyzing and sorting.

Original based on:
Homepage: http://www.d-herrmann.de/projects/monolog-mysql-handler/

# Disclaimer
This is a very simple handler for monolog. This version works for custom plugin development, but I would not advise to distribute this code in a public repository for general use on high traffic sites. You have been warned.

# Installation
monolog-wordpress is available via composer. Just add the following line to your required section in composer.json and do a `php composer.phar update` or your choice of composer update method.

```
"bradmkjr/monolog-wordpress": "^2.1.0"
```

# Versions
Since Monolog v2 broke compatibility with PHP versions before v7.1 some may want to keep using Monolog v1. **monolog-wordpress** is therefore offered in two major versions:
* [v1](https://github.com/bradmkjr/monolog-wordpress/tree/v1) - compatible with Monolog v1 and PHP v5.3 or later.
* [v2](https://github.com/bradmkjr/monolog-wordpress/tree/master) - compatible with Monolog v2 and PHP v7.1 or later.

Apart from the compatibility differences stated above the features of v1 and v2 are going to be kept the same as much as possible.

# Usage
Just use it as any other Monolog Handler, push it to the stack of your Monolog Logger instance. The Handler however has some parameters:

- `$wpdb`: The instance of your DB connection. To use the global connection of WordPress, use `null`. Otherwise, use a `\wpdb` instance. _Default: `null`_
- `$table`:  Name of the database table to store the logs in. The 'wp_' (or other configured) prefix will be added automatically. _Default: `'logs'`_
- `$additionalFields`: simple array of additional database fields, which should be stored in the database. The columns are created automatically, and the fields can later be used in the extra context section of a record. See examples below. _Defaults to an empty `array()`_
- `$level`: The minimum logging level at which this handler will be triggered. Can be any of the standard Monolog logging levels. Use Monologs statically defined contexts. _Defaults to `Logger::DEBUG`_
- `$bubble`: Whether the messages that are handled can bubble up the stack or not. _Defaults to `true`_

# Examples
Given that the global `$wpdb` is your database instance, you could use the class as follows:

```php
// Import class
use WordPressHandler\WordPressHandler;

// Create WordPressHandler
$wordPressHandler = new WordPressHandler(null, "log", ['username', 'userid'], \Monolog\Logger::DEBUG);

// Configure maximum number of rows to keep (old entries are deleted when reached)
$wordPressHandler->conf_table_size_limiter( 250000 );

// Setup array of extra fields
$record = ['extra' => []];

// Create database table if needed, add extra fields from above
$wordPressHandler->initialize($record);

// Create Logger
$context = 'channel-name';
$logger = new \Monolog\Logger($context);

// Add WordPressHandler as the Handler for the Logger
$logger->pushHandler($wordPressHandler);

// Now you can use the logger, and further attach additional information
$logger->warning("This is a great message, woohoo!", ['username'  => 'John Doe', 'userid'  => 245]);
```

Required code to set up tables on plugin activation:

```php
require __DIR__.'/vendor/autoload.php';

// Create the logs table if it doesn't already exist on plugin activation
register_activation_hook(__FILE__, 'my_plugin_activation');
function my_plugin_activation() {
    $handler = new \WordPressHandler\WordPressHandler(
        null, "logs",
        array('username', 'userid'),
        \Monolog\Logger::DEBUG
    );

    // setup array of extra fields
    $record = array('extra' => array());

    // creates database table if needed, add extra fields from above
    $handler->initialize($record);
}
```

Now somewhere else in my plugin where I want to use the logger:
```php
$logger = new \Monolog\Logger('channel');
$handler = new \WordPressHandler\WordPressHandler(
    null, "logs",
    [],
    \Monolog\Logger::DEBUG
);
$handler->initialized = true; // Don't do any extra work - we've already done it.
$logger->pushHandler($handler);

$logger->warning('Some message');
```

Example code to delete tables on plugin deactivation:

```php
register_uninstall_hook(__FILE__, 'my_plugin_uninstall');
function my_plugin_uninstall()
{
    require __DIR__."/vendor/autoload.php";

    $handler = new \WordPressHandler\WordPressHandler(
        null, "logs",
        [],
        \Monolog\Logger::DEBUG
    );
    $handler->uninitialize();
}
```


Example to use in your custom WordPress Plugin

```php
use WordPressHandler\WordPressHandler;

require __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', 'demo_function' );
function demo_function(){
    // Create a WordPressHandler instance
    $wordPressHandler = new WordPressHandler(null, "log", ['app', 'version'], \Monolog\Logger::DEBUG);
    
    // Create logger
    $context = 'test-plugin-logging';
    $logger = new \Monolog\Logger($context);
    
    // Add WordPressHandler as the Handler for the Logger
    $logger->pushHandler($wordPressHandler);
    
    // Now you can use the logger, and further attach additional information
    $logger->warning("This is a great message, woohoo!", ['app'  => 'Test Plugin', 'version'  => '2.4.5']);
}
```

# License
This tool is free software and is distributed under the MIT license. Please have a look at the LICENSE file for further information.
