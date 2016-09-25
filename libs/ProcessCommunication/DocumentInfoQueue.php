<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午10:57
 */

namespace PhCrawler\ProcessCommunication;


use PDO;
use PDOStatement;
use Exception;
use PhCrawler\DocumentInfo;

/**
 * Class DocumentInfoQueue
 *
 * @package PhCrawler\ProcessCommunication
 */
class DocumentInfoQueue
{
    /**
     * @var PDO
     */
    protected $PDO;

    /**
     * @var string
     */
    protected $sqlite_db_file;

    /**
     * @var bool
     */
    protected $prepared_statements_created = false;


    /**
     * @var PDOStatement
     */
    protected $preparedInsertStatement;


    /**
     * @var PDOStatement
     */
    protected $preparedSelectStatement;

    /**
     * @var null|string
     */
    protected $working_directory = null;

    /**
     * @var int
     */
    protected $queue_max_size = 50;

    /**
     * Initiates a DocumentInfoQueue
     *
     * @param string $file            The SQLite-fiel to use.
     * @param  bool  $create_tables   Defines whether all necessary tables should be created
     */
    public function __construct($file, $create_tables = false)
    {
        $this->sqlite_db_file = $file;
        $this->working_directory = dirname($file)."/";
        $this->openConnection($create_tables);
    }

    /**
     * Returns the current number of DocumentInfo-objects in the queue
     */
    public function getDocumentInfoCount()
    {
        $Result = $this->PDO->query("SELECT count(id) as sum FROM document_infos;");
        $row = $Result->fetch(PDO::FETCH_ASSOC);
        $Result->closeCursor();

        return $row["sum"];
    }

    /**
     * Adds a DocumentInfo-object to the queue
     * @param $DocInfo DocumentInfo
     */
    public function addDocumentInfo(DocumentInfo $DocInfo)
    {
        // If queue is full -> wait a little
        while ($this->getDocumentInfoCount() >= $this->queue_max_size)
        {
            usleep(500000);
        }

        $this->createPreparedStatements();

        $ser = serialize($DocInfo);

        $this->PDO->exec("BEGIN EXCLUSIVE TRANSACTION");
        $this->preparedInsertStatement->bindParam(1, $ser, PDO::PARAM_LOB);
        $this->preparedInsertStatement->execute();
        $this->preparedSelectStatement->closeCursor();
        $this->PDO->exec("COMMIT");
    }


    /**
     * @return mixed|null
     */
    public function getNextDocumentInfo()
    {
        $this->createPreparedStatements();

        $this->preparedSelectStatement->execute();
        $this->preparedSelectStatement->bindColumn("document_info", $doc_info, PDO::PARAM_LOB);
        $this->preparedSelectStatement->bindColumn("id", $id);
        $row = $this->preparedSelectStatement->fetch(PDO::FETCH_BOUND);
        $this->preparedSelectStatement->closeCursor();

        if ($id == null)
        {
            return null;
        }

        $this->PDO->exec("DELETE FROM document_infos WHERE id = ".$id.";");

        $DocInfo = unserialize($doc_info);

        return $DocInfo;
    }

    /**
     * Creates all prepared statemenst
     */
    protected function createPreparedStatements()
    {
        if ($this->prepared_statements_created == false)
        {
            $this->preparedInsertStatement = $this->PDO->prepare("INSERT INTO document_infos (document_info) VALUES (?);");
            $this->preparedSelectStatement = $this->PDO->prepare("SELECT * FROM document_infos limit 1;");

            $this->prepared_statements_created = true;
        }
    }

    /**
     * @param bool $create_tables
     *
     * @throws \Exception
     */
    protected function openConnection($create_tables = false)
    {
        // Open sqlite-file
        try
        {
            $this->PDO = new PDO("sqlite:".$this->sqlite_db_file);
        }
        catch (Exception $e)
        {
            throw new Exception("Error creating SQLite-cache-file, ".$e->getMessage().", try installing sqlite3-extension for PHP.");
        }

        $this->PDO->exec("PRAGMA journal_mode = OFF");

        $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        $this->PDO->setAttribute(PDO::ATTR_TIMEOUT, 100);

        if ($create_tables == true)
        {
            $this->PDO->exec("CREATE TABLE IF NOT EXISTS document_infos (id integer PRIMARY KEY AUTOINCREMENT,
                                                                   document_info blob);");
            $this->PDO->exec("ANALYZE;");
        }
    }
}
