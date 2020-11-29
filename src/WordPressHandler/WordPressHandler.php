<?php
namespace WordPressHandler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * This class is a handler for Monolog, which can be used
 * to write records in a MySQL table with WordPress Functions
 *
 * Class WordPressHandler
 * @package bradmkjr\WordPressHandler
 */
class WordPressHandler extends AbstractProcessingHandler
{
    /**
     * @var bool defines whether the MySQL connection is been initialized
     */
    public $initialized = false;
    /**
     * @var \wpdb wpdb object of database connection
     */
    protected $wpdb;
    /**
     * @var string the table to store the logs in
     */
    private $table = 'logs';
    /**
     * @var string the table prefix to store the logs in
     */
    private $prefix = 'wp_';
    /**
     * @var string[] additional fields to be stored in the database
     *
     * For each field $field, an additional context field with the name $field
     * is expected along the message, and further the database needs to have these fields
     * as the values are stored in the column name $field.
     */
    private $additionalFields = array();
    /**
     * @var int Defines the maximum number of rows allowed in the log table. 0 means no limit
     */
    protected $max_table_rows = 0;
    /**
     * @var int Defines the number of rows deleted when limit is reached.
     *          Do not choose a value too low, because it may generate huge database overhead!
     */
    protected $truncate_batch_size = 1;
    /**
     * Constructor of this class, sets own fields and calls parent constructor
     *
     * @param \wpdb|null $custom_wpdb      The {@see \wpdb} object of database connection.
     *                                     Set to `null` to automatically use the global $wpdb of WordPress.
     *                                     Default: null
     * @param string     $table            Name of the database table to store the logs in.
     *                                     The 'wp_' (or other configured) prefix will be added automatically.
     *                                     Default: 'logs'
     * @param string[]   $additionalFields Additional Context Parameters to store in database
     *                                     Default: empty array i.e. no additional fields
     * @param int|string $level            The minimum logging level at which this handler will be triggered.
     *                                     Default: {@see Logger::DEBUG}
     * @param bool       $bubble           Whether the messages that are handled can bubble up the stack or not.
     *                                     Default: true
     */
    public function __construct(
        $custom_wpdb = null,
        $table = 'logs',
        $additionalFields = array(),
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        if ( ! is_null($custom_wpdb) ) {
            if (  ( $custom_wpdb instanceof \wpdb ) ) {
                $this->wpdb = $custom_wpdb;
            }
            else {
                throw new \InvalidArgumentException('$custom_wpdb must be an instance of the Wordpress wpdb class.', 1606644510);
            }
        }
        else {
            global $wpdb;
            if ( isset($wpdb) ) {
                if (  ( $wpdb instanceof \wpdb ) ) {
                    $this->wpdb = $wpdb;
                }
                else {
                    throw new \RuntimeException('The global $wpdb is not an instance of the Wordpress wpdb class.', 1606644515);
                }
            }
            else {
                throw new \RuntimeException('$custom_wpdb is not provided and global $wpdb is not available.', 1606644520);
            }
        }
        $this->table = $table;
        $this->prefix = $this->wpdb->prefix;

        $this->additionalFields = $additionalFields;
        parent::__construct($level, $bubble);
    }
    
    /**
     * Configure the limiter for the maximum number of table rows used to collect log entries.
     *
     * @param int      $max_table_rows      The max number of rows to accumulate.
     *                                      Use 0 (or any negative number) to disable limit.
     * @param null|int $truncate_batch_size Optional.
     *                                      This defines the number of rows deleted when the limit is reached.
     *                                      Once the limit is reached, rows are deleted every time this number of log
     *                                      entries added.
     *                                      Do not set it to a small number, because deleting rows too often can create
     *                                      significant performance issues.
     *                                      Recommended minimum value is between 100 and few thousands.
     *                                      Default: set to 10% of $max_table_rows
     */
    public function conf_table_size_limiter( $max_table_rows, $truncate_batch_size = null ) {
        if ( ! is_int( $max_table_rows ) ) {
            throw new \InvalidArgumentException('Maximum number of table rows must be an integer.');
        }
        if ( is_null( $truncate_batch_size ) ) {
            $truncate_batch_size = (int) ( $max_table_rows / 10 );
        }
        if ( ! is_int( $truncate_batch_size ) ) {
            throw new \InvalidArgumentException('Truncate batch size must be an integer or null.');
        }
        $this->max_table_rows = max( 0, $max_table_rows );
        $this->truncate_batch_size = max( 1, $truncate_batch_size );
    }
    /**
     * Set the limit for the number of table rows used to collect log entries.
     *
     *
     * @param int      $max_table_rows      The max number of rows to accumulate.
     *                                      Use 0 (or any negative number) to disable limit.
     *
     * @deprecated Use {@see conf_table_size_limiter()} instead.
     */
    public function set_max_table_rows( $max_table_rows ) {
        $this->conf_table_size_limiter( $max_table_rows );
    }
    /**
     * Returns the full log tables name
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->prefix . $this->table;
    }
    /**
     * Initializes this handler by creating the table if it not exists
     */
    public function initialize(array $record)
    {

        // referenced
        // https://codex.wordpress.org/Creating_Tables_with_Plugins

        // $this->wpdb->exec(
        //     'CREATE TABLE IF NOT EXISTS `'.$this->table.'` '
        //     .'(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)'
        // );

        $charset_collate = $this->wpdb->get_charset_collate();

        $table_name = $this->get_table_name();

        // allow for Extra fields
        $extraFields = '';
        foreach ($record['extra'] as $key => $val) {
            $extraFields.=",\n`$key` TEXT NULL DEFAULT NULL";
        }

        // additional fields
        $additionalFields = '';
        foreach ($this->additionalFields as $f) {
            $additionalFields.=",\n`$f` TEXT NULL DEFAULT NULL";
        }

        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            channel VARCHAR(255),
            level INTEGER,
            message LONGTEXT,
            time INTEGER UNSIGNED$extraFields$additionalFields,
            PRIMARY KEY  (id)
            ) $charset_collate;";


        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        $this->initialized = true;
    }
    /**
     * Uninitializes this handler by deleting the table if it exists
     */
    public function uninitialize()
    {
        $table_name = $this->get_table_name();
        $sql = "DROP TABLE IF EXISTS $table_name;";

        if (!is_null($this->wpdb)) {
            $this->wpdb->query($sql);
        }
    }
    /**
     * Deletes the oldest records from the log table to ensure there are no more
     * rows than the defined limit.
     *
     * Use {@see set_max_table_rows()} to configure the limit!
     *
     * @return boolean True if rows were deleted, false otherwise.
     */
    protected function maybe_truncate() {
        if ( $this->max_table_rows <= 0 ) {
            return false;
        }
        
        $table_name = $this->get_table_name();
        
        $sql = "SELECT count(*) FROM {$table_name};";
        $count = $this->wpdb->get_var($sql);
        
        if ( is_numeric( $count ) && $this->max_table_rows <= (int) $count ) {
            $offset = $this->max_table_rows - $this->truncate_batch_size;
            // using `LIMIT -1`, `LIMIT 0`, `LIMIT NULL` may not be compatible with all db systems
            // deleting 10000 rows in one go is good enough anyway, it'll converge pretty fast
            $sql = "DELETE FROM {$table_name} WHERE `id` IN ( SELECT * FROM (SELECT `id` FROM {$table_name} ORDER BY `id` DESC LIMIT 10000 OFFSET {$offset}) as `workaround_subquery_for_older_mysql_versions` );";
            return false !== $this->wpdb->query($sql);
        }
        return false;
    }
    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  $record[]
     * @return void
     */
    protected function write(array $record)
    {
        if (!$this->initialized) {
            $this->initialize($record);
        }
        //'context' contains the array
        $contentArray = array(
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => (isset($record['formatted']['message'])) ? $record['formatted']['message'] : $record['message'],
            'time' => $record['datetime']->format('U')
        );

        // Make sure to use the formatted values for context and extra, if available
        $recordExtra = (isset($record['formatted']['extra'])) ? $record['formatted']['extra'] : $record['extra'];
        $recordContext = (isset($record['formatted']['context'])) ? $record['formatted']['context'] : $record['context'];
    
        $recordContExtra = array_merge( $recordExtra, $recordContext );

        // json encode values as needed
        array_walk($recordContExtra, function(&$value, $key) {
            if(is_array($value) || $value instanceof \Traversable) {
                $value = json_encode($value);
            }
        });

        $contentArray = $contentArray + $recordContExtra;

        if(count($this->additionalFields) > 0) {
            //Fill content array with "null" values if not provided
            $contentArray = $contentArray + array_combine(
                $this->additionalFields,
                array_fill(0, count($this->additionalFields), null)
            );
        }

        $table_name = $this->get_table_name();

        $this->wpdb->insert( $table_name, $contentArray );
        $this->maybe_truncate();
    }
}
