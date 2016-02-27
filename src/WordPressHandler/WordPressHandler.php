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
    private $initialized = false;
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
        $this->additionalFields = $additionalFields;
        parent::__construct($level, $bubble);
    }
    /**
     * Initializes this handler by creating the table if it not exists
     */
    private function initialize()
    {
        $this->wpdb->exec(
            'CREATE TABLE IF NOT EXISTS `'.$this->table.'` '
            .'(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)'
        );
        //Read out actual columns
        $actualFields = array();
        $rs = $this->wpdb->query('SELECT * FROM `'.$this->table.'` LIMIT 0');
        for ($i = 0; $i < $rs->columnCount(); $i++) {
            $col = $rs->getColumnMeta($i);
            $actualFields[] = $col['name'];
        }
        //Calculate changed entries
        $removedColumns = array_diff(
            $actualFields,
            $this->additionalFields,
            array('channel', 'level', 'message', 'time')
        );
        $addedColumns = array_diff($this->additionalFields, $actualFields);
        //Remove columns
        if (!empty($removedColumns)) {
            foreach ($removedColumns as $c) {
                $this->wpdb->exec('ALTER TABLE `'.$this->table.'` DROP `'.$c.'`;');
            }
        }
        //Add columns
        if (!empty($addedColumns)) {
            foreach ($addedColumns as $c) {
                $this->wpdb->exec('ALTER TABLE `'.$this->table.'` add `'.$c.'` TEXT NULL DEFAULT NULL;');
            }
        }
        //Prepare statement
        $columns = "";
        $fields = "";
        foreach ($this->additionalFields as $f) {
            $columns.= ", $f";
            $fields.= ", :$f";
        }
        $this->statement = $this->wpdb->prepare(
            'INSERT INTO `'.$this->table.'` (channel, level, message, time'.$columns.')
            VALUES (:channel, :level, :message, :time'.$fields.')'
        );
        $this->initialized = true;
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
            $this->initialize();
        }
        //'context' contains the array
        $contentArray = array_merge(array(
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => $record['message'],
            'time' => $record['datetime']->format('U')
        ), $record['context']);
        //Fill content array with "null" values if not provided
        $contentArray = $contentArray + array_combine(
            $this->additionalFields,
            array_fill(0, count($this->additionalFields), null)
        );
        $this->statement->execute($contentArray);
    }
}