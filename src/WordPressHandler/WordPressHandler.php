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
     * @var string ip address to store in database
     */
	private $ip_address = 'unknown';
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
		
		$ip_address = $this->get_ip_address();
		$this->ip_address = ($ip_address == '::1') ? 'localhost' : $ip_address;

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
        foreach ($record['extra'] as $ef) {
            if( isset($ef['name']) ){
				if( isset($ef['type']) ){
					$extraFields.=",\n{$ef['name']} {$ef['type']}";
				}else{
					$extraFields.=",\n{$ef['name']} TEXT NULL DEFAULT NULL";
				}
			}
        }

        // additional fields
        $additionalFields = '';
        foreach ($this->additionalFields as $af) {
            if( isset($af['name']) ){
				if( isset($af['type']) ){
					$additionalFields.=",\n{$af['name']} {$af['type']}";
				}else{
					$additionalFields.=",\n{$af['name']} TEXT NULL DEFAULT NULL";
				}
			}
        }

        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            channel VARCHAR(255),
            level INTEGER,
            message LONGTEXT,
            time INTEGER UNSIGNED
            ip VARCHAR(255) DEFAULT NULL$extraFields$additionalFields,
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
            'time' => $record['datetime']->format('U'),
            'ip' => $this->ip_address
        ), $record['context']);

        // extra out formatted or unformatted extra values
        $recordExtra = (isset($record['formatted']['extra'])) ? $record['formatted']['extra'] : $record['extra'];

        // json encode values as needed
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
	
	/**
     * Returns the ip address of user
     *
     * @return string
     */
	public function get_ip_address() {
		// check for shared internet/ISP IP
		if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		// check for IPs passing through proxies
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// check if multiple ips exist in var
			if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
				$iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
				foreach ($iplist as $ip) {
					if ($this->validate_ip($ip))
						return $ip;
				}
			} else {
				if ($this->validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']))
					return $_SERVER['HTTP_X_FORWARDED_FOR'];
			}
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED']) && $this->validate_ip($_SERVER['HTTP_X_FORWARDED']))
			return $_SERVER['HTTP_X_FORWARDED'];
		if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
			return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && $this->validate_ip($_SERVER['HTTP_FORWARDED_FOR']))
			return $_SERVER['HTTP_FORWARDED_FOR'];
		if (!empty($_SERVER['HTTP_FORWARDED']) && $this->validate_ip($_SERVER['HTTP_FORWARDED']))
			return $_SERVER['HTTP_FORWARDED'];

		// return unreliable ip since all else failed
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	public function validate_ip($ip) {
		if (strtolower($ip) === 'unknown')
			return false;

		// generate ipv4 network address
		$ip = ip2long($ip);

		// if the ip is set and not equivalent to 255.255.255.255
		if ($ip !== false && $ip !== -1) {
			// make sure to get unsigned long representation of ip
			// due to discrepancies between 32 and 64 bit OSes and
			// signed numbers (ints default to signed in PHP)
			$ip = sprintf('%u', $ip);
			// do private network range checking
			if ($ip >= 0 && $ip <= 50331647) return false;
			if ($ip >= 167772160 && $ip <= 184549375) return false;
			if ($ip >= 2130706432 && $ip <= 2147483647) return false;
			if ($ip >= 2851995648 && $ip <= 2852061183) return false;
			if ($ip >= 2886729728 && $ip <= 2887778303) return false;
			if ($ip >= 3221225984 && $ip <= 3221226239) return false;
			if ($ip >= 3232235520 && $ip <= 3232301055) return false;
			if ($ip >= 4294967040) return false;
		}
		return true;
	}
}
