<?php
namespace WordPressHandler;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
// use PDO;
// use PDOStatement;

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
     * @var WPDB wpdb object of database connection
     */
    protected $wpdb;
    /**
     * @var PDOStatement statement to insert a new record
     */
    // private $statement;
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
     * Constructor of this class, sets the PDO and calls parent constructor
     *
     * @param PDO $wpdb                  PDO Connector for the database
     * @param bool $table               Table in the database to store the logs in
     * @param array $additionalFields   Additional Context Parameters to store in database
     * @param bool|int $level           Debug level which this handler should store
     * @param bool $bubble
     */
    public function __construct(
        $wpdb = null,
        $table,
        $additionalFields = array(),
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        if (!is_null($wpdb)) {
            $this->wpdb = $wpdb;
        }
        $this->table = $table;
        $this->prefix = $this->wpdb->prefix;

        $this->additionalFields = $additionalFields;
        parent::__construct($level, $bubble);
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
    public function initialize()
    {

        // referenced
        // https://codex.wordpress.org/Creating_Tables_with_Plugins

        // $this->wpdb->exec(
        //     'CREATE TABLE IF NOT EXISTS `'.$this->table.'` '
        //     .'(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)'
        // );

        $charset_collate = $this->wpdb->get_charset_collate();

        $table_name = $this->get_table_name();

        $additionalFields = '';
        foreach ($this->additionalFields as $f) {
            $additionalFields.=",\n$f TEXT NULL DEFAULT NULL";
        }

        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            channel VARCHAR(255),
            level INTEGER,
            message LONGTEXT,
            time INTEGER UNSIGNED$additionalFields,
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
        $contentArray = array_merge(array(
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => $record['message'],
            'time' => $record['datetime']->format('U')
        ), $record['context']);

        $recordExtra = (isset($record['formatted']['extra'])) ? $record['formatted']['extra'] : $record['extra'];

        array_walk($recordExtra, function(&$value, $key) {
        	if(is_array($value) || $value instanceof \Traversable) {
        		$value = json_encode($value);
        	}
        });

        $contentArray = $contentArray + $recordExtra;

        if(count($this->additionalFields) > 0) {
	        //Fill content array with "null" values if not provided
	        $contentArray = $contentArray + array_combine(
	            $this->additionalFields,
	            array_fill(0, count($this->additionalFields), null)
	        );
        }

        $table_name = $this->get_table_name();

        $this->wpdb->insert( $table_name, $contentArray );

    }
}
