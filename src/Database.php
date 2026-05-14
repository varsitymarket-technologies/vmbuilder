<?php

// Database.php

#   TITLE   : Database Manager    
#   DESC    : This manages The Defined SQL Database using the command line program 
#   PROPRIETOR: VARSITYMARKET_TECHNOLOGIES
#   VERSION : 1.3.1.1
#   AUTHOR  : HARDY HASTINGS  
#   RELEASE : 2026/05/14

class Database {
    private static ?PDO $pdo = null;

    public static function connect(): PDO {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA foreign_keys=ON');
        }
        return self::$pdo;
    }

    public static function migrate(): void {
        $pdo = self::connect();

        $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            settings TEXT NOT NULL DEFAULT '{}',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            components TEXT NOT NULL DEFAULT '[]',
            seo TEXT NOT NULL DEFAULT '{}',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS form_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            page_id INTEGER NOT NULL,
            data TEXT NOT NULL DEFAULT '{}',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
        )");
    }
}


define('__DATABASE_SOURCE__', ("vm.engine").".sqlite3");

class database_manager
{
    private PDO $pdo; // PDO object for database connection
    private string $dbPath; // Path to the SQLite database file

    /**
     * Override Connection for SQLiteManager.
     *
     * @param string $dbFileName The name of the SQLite database file (e.g., 'mydatabase.sqlite').
     */
    public function override_connection(string $dbFileName = null)
    {   
        $this->dbPath = $dbFileName; 
        $this->connect(); // Establish database connection
    }

    /**
     * Constructor for SQLiteManager.
     *
     * @param string $dbFileName The name of the SQLite database file (e.g., 'mydatabase.sqlite').
     */
    public function __construct(string $dbFileName = null)
    {   
        if ($dbFileName == null){
            $dbFileName = __DATABASE_SOURCE__ ; 
        }

        // Define the database path relative to the script's execution directory
        if (file_exists($dbFileName)){
            $this->dbPath = $dbFileName;
        }else{
            $this->dbPath = __DIR__ . DIRECTORY_SEPARATOR . $dbFileName;   
        }
        $this->connect(); // Establish database connection
    }

    /**
     * Connects to the SQLite database.
     * If the database file does not exist, it will be created.
     */
    private function connect()
    {
        try {
            // Check if the database file exists, if not, it will be created by PDO
            if (!file_exists($this->dbPath)) {
                 #"Creating new SQLite database: " . $this->dbPath ;
                // No need to explicitly create the file, PDO will do it on connection
            }

            // Establish PDO connection to the SQLite database
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            // Set error mode to exceptions for better error handling
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return TRUE; // Return true on successful connection
        } catch (PDOException $e) {
            // Catch and display any connection errors
            trigger_error("Database connection failed: " . $e->getMessage(), E_USER_ERROR);
            return FALSE; // Return false on connection failure
            exit(1); // Exit the script on connection failure
        }
    }

    /**
     * Executes a CREATE TABLE SQL statement.
     *
     * @param string $tableName The name of the table to create.
     * @param array $columns An associative array where keys are column names and values are their types (e.g., ['id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'name' => 'TEXT']).
     * @return bool True on success, false on failure.
     */
    public function createTable(string $tableName, array $columns)
    {
        if (empty($columns)) {
            trigger_error("No columns provided for table creation.", E_USER_WARNING);
            return false;
        }

        // Build the column definitions string
        $columnDefinitions = [];
        foreach ($columns as $columnName => $columnType) {
            $columnDefinitions[] = "$columnName $columnType";
        }
        $columnsSql = implode(", ", $columnDefinitions);

        // Construct the CREATE TABLE SQL query
        $sql = "CREATE TABLE IF NOT EXISTS $tableName ($columnsSql);";

        try {
            // Execute the SQL statement
            $this->pdo->exec($sql);
            #"Table '$tableName' created successfully (or already exists).\n";
            return true;
        } catch (PDOException $e) {
            // Catch and display any errors during table creation
            #"Error creating table '$tableName': " . $e->getMessage() . "\n";
            trigger_error("Error creating table '$tableName': " . $e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Executes any arbitrary SQL statement (INSERT, UPDATE, DELETE, etc.).
     *
     * @param string $sql The SQL statement to execute.
     * @return int|false The number of affected rows for INSERT/UPDATE/DELETE, or false on error.
     */
    public function executeSql(string $sql)
    {
        #"Executing SQL: $sql\n";
        try {
            // Execute the SQL statement and return affected rows
            $affectedRows = $this->pdo->exec($sql);
            #"SQL executed successfully. Affected rows: $affectedRows\n";
            return $affectedRows;
        } catch (PDOException $e) {
            // Catch and display any errors during SQL execution
            trigger_error("Error executing SQL: " . $e->getMessage() . "\n"); 
            return false;
        }
    }

    /**
     * Executes a SELECT query and returns the results.
     *
     * @param string $sql The SELECT SQL query.
     * @param array $params Optional array of parameters for prepared statements.
     * @return array An array of associative arrays representing the query results.
     */
    public function query(string $sql, array $params = [])
    {
        try {
            // Prepare the SQL statement
            $stmt = $this->pdo->prepare($sql);
            // Execute the statement with parameters
            $stmt->execute($params);
            // Fetch all results as associative arrays
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $results;
        } catch (PDOException $e) {
            // Catch and display any errors during query execution
            # "Error executing query: " . $e->getMessage() . "\n";
            trigger_error("Error executing query: " . $e->getMessage() . "\n"); 
            return [];
        }
    }
}

?>