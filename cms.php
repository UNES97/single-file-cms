<?php
/**
 * Modern Single-File PHP CMS with SQLite Database
 * 
 * A beautiful, user-friendly content management system in a single PHP file
 * Features: Setup wizard, authentication, dynamic tables, media management with linking
 */

session_start();

// Configuration
define('DB_FILE', 'cms_database.sqlite');
define('MEDIA_DIR', 'media');

class ModernCMS {
    private $db;
    private $isSetup = false;
    
    public function __construct() {
        $this->initDatabase();
        $this->createMediaDirectory();
        $this->runMigrations();
        $this->isSetup = $this->checkSetupComplete();
    }
    
    /**
     * Initialize SQLite database connection
     */
    private function initDatabase() {
        try {
            $this->db = new PDO('sqlite:' . DB_FILE);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create media directory if it doesn't exist
     */
    private function createMediaDirectory() {
        if (!is_dir(MEDIA_DIR)) {
            if (!mkdir(MEDIA_DIR, 0755, true)) {
                error_log("Failed to create media directory: " . MEDIA_DIR);
            }
        }
    }
    
    /**
     * Setup default languages
     */
    private function setupDefaultLanguages() {
        // Check if any languages already exist
        $existingCount = $this->db->query("SELECT COUNT(*) FROM languages")->fetchColumn();
        if ($existingCount > 0) {
            return; // Languages already exist, skip setup
        }
        
        $defaultLanguages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => 1],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_default' => 0],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'is_default' => 0],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => 0],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'is_default' => 0],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'is_default' => 0],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'is_default' => 0],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'is_default' => 0],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'is_default' => 0],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'is_default' => 0]
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO languages (code, name, native_name, is_default, is_active) VALUES (?, ?, ?, ?, 1)");
        foreach ($defaultLanguages as $lang) {
            $stmt->execute([$lang['code'], $lang['name'], $lang['native_name'], $lang['is_default']]);
        }
    }
    
    /**
     * Run database migrations for existing installations
     */
    private function runMigrations() {
        try {
            // Check if languages table exists
            $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='languages'");
            if (!$stmt->fetch()) {
                // Create language support tables
                $languageQueries = [
                    "CREATE TABLE IF NOT EXISTS languages (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        code TEXT UNIQUE NOT NULL,
                        name TEXT NOT NULL,
                        native_name TEXT NOT NULL,
                        is_default BOOLEAN DEFAULT 0,
                        is_active BOOLEAN DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )",
                    
                    "CREATE TABLE IF NOT EXISTS content_translations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        table_name TEXT NOT NULL,
                        record_id INTEGER NOT NULL,
                        field_name TEXT NOT NULL,
                        language_code TEXT NOT NULL,
                        translated_value TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(table_name, record_id, field_name, language_code)
                    )"
                ];
                
                foreach ($languageQueries as $query) {
                    $this->db->exec($query);
                }
                
                // Setup default languages
                try {
                    $this->setupDefaultLanguages();
                } catch (PDOException $e) {
                    // Ignore duplicate language errors
                    error_log("Language setup warning: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            // Silently fail - migrations are optional for compatibility
            error_log("Migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check if setup is complete
     */
    private function checkSetupComplete() {
        try {
            $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            if ($stmt->fetch()) {
                $stmt = $this->db->query("SELECT COUNT(*) as count FROM users");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['count'] > 0;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Create reserved system tables
     */
    private function createSystemTables() {
        $queries = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Media table with additional metadata
            "CREATE TABLE IF NOT EXISTS media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                path TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER DEFAULT 0,
                tags TEXT DEFAULT '',
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Table field metadata for tracking media fields and relationships
            "CREATE TABLE IF NOT EXISTS table_field_meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_name TEXT NOT NULL,
                field_name TEXT NOT NULL,
                field_type TEXT NOT NULL,
                is_media_field BOOLEAN DEFAULT 0,
                media_type TEXT DEFAULT NULL,
                is_foreign_key BOOLEAN DEFAULT 0,
                foreign_table TEXT DEFAULT NULL,
                foreign_display TEXT DEFAULT NULL
            )",
            
            // Languages table for multi-language support
            "CREATE TABLE IF NOT EXISTS languages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                native_name TEXT NOT NULL,
                is_default BOOLEAN DEFAULT 0,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Content translations table
            "CREATE TABLE IF NOT EXISTS content_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_name TEXT NOT NULL,
                record_id INTEGER NOT NULL,
                field_name TEXT NOT NULL,
                language_code TEXT NOT NULL,
                translated_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(table_name, record_id, field_name, language_code)
            )"
        ];
        
        foreach ($queries as $query) {
            $this->db->exec($query);
        }
        
        // Add tags column to existing media table if it doesn't exist
        try {
            $this->db->exec("ALTER TABLE media ADD COLUMN tags TEXT DEFAULT ''");
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }
    
    /**
     * Create user-defined tables with media linking support
     */
    private function createUserTables($tables) {
        foreach ($tables as $tableName => $config) {
            $fields = $config['fields'];
            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (id INTEGER PRIMARY KEY AUTOINCREMENT";
            
            foreach ($fields as $field) {
                $fieldName = $this->sanitizeFieldName($field['name']);
                $fieldType = $this->mapFieldType($field['type']);
                $sql .= ", `{$fieldName}` {$fieldType}";
            }
            
            $sql .= ")";
            $this->db->exec($sql);
            
            // Store field metadata
            foreach ($fields as $field) {
                $fieldName = $this->sanitizeFieldName($field['name']);
                $isMediaField = in_array($field['type'], ['media_single', 'media_multiple']);
                $mediaType = $isMediaField ? ($field['type'] === 'media_single' ? 'single' : 'multiple') : null;
                $isForeignKey = $field['type'] === 'foreign_key';
                
                // Get foreign table from explicit configuration
                $foreignTable = null;
                $foreignDisplay = null;
                if ($isForeignKey) {
                    $foreignTable = $field['foreign_table'] ?? null;
                    $foreignDisplay = $field['foreign_display'] ?? null;
                }
                
                $stmt = $this->db->prepare("INSERT INTO table_field_meta (table_name, field_name, field_type, is_media_field, media_type, is_foreign_key, foreign_table, foreign_display) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tableName, $fieldName, $field['type'], $isMediaField ? 1 : 0, $mediaType, $isForeignKey ? 1 : 0, $foreignTable, $foreignDisplay]);
            }
        }
    }
    
    private function sanitizeFieldName($name) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }
    
    private function mapFieldType($type) {
        $typeMap = [
            'text' => 'TEXT',
            'textarea' => 'TEXT',
            'number' => 'INTEGER',
            'decimal' => 'REAL',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'boolean' => 'BOOLEAN',
            'media' => 'INTEGER',
            'media_single' => 'INTEGER',
            'media_multiple' => 'TEXT',
            'foreign_key' => 'INTEGER'
        ];
        
        return isset($typeMap[$type]) ? $typeMap[$type] : 'TEXT';
    }
    
    /**
     * Handle setup wizard submission
     */
    private function handleSetup() {
        if ($_POST['action'] === 'setup') {
            $username = trim($_POST['admin_username']);
            $password = trim($_POST['admin_password']);
            
            if (empty($username) || empty($password)) {
                return ['error' => 'Username and password are required'];
            }
            
            if (strlen($password) < 6) {
                return ['error' => 'Password must be at least 6 characters long'];
            }
            
            $this->createSystemTables();
            try {
                $this->setupDefaultLanguages();
            } catch (PDOException $e) {
                // Ignore duplicate language errors during setup
                error_log("Language setup warning during setup: " . $e->getMessage());
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$username, $passwordHash]);
            
            if (!empty($_POST['custom_tables'])) {
                $tables = json_decode($_POST['custom_tables'], true);
                if ($tables) {
                    $this->createUserTables($tables);
                }
            }
            
            $this->isSetup = true;
            return ['success' => 'CMS setup completed successfully! You can now login with your credentials.'];
        }
        
        return null;
    }
    
    /**
     * Handle authentication
     */
    private function handleAuth() {
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                return ['success' => 'Welcome back! Login successful.'];
            } else {
                return ['error' => 'Invalid username or password. Please try again.'];
            }
        } elseif ($_POST['action'] === 'logout') {
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        return null;
    }
    
    private function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    private function getUserTables() {
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('users', 'media', 'sqlite_sequence', 'table_field_meta', 'languages', 'content_translations')");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getTableStructure($tableName) {
        $stmt = $this->db->query("PRAGMA table_info(`{$tableName}`)");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTableFieldMetadata($tableName) {
        $stmt = $this->db->prepare("SELECT * FROM table_field_meta WHERE table_name = ?");
        $stmt->execute([$tableName]);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['field_name']] = $row;
        }
        return $result;
    }
    
    private function getActiveLanguages() {
        try {
            $stmt = $this->db->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Return empty array if languages table doesn't exist yet
            return [];
        }
    }
    
    private function getDefaultLanguage() {
        try {
            $stmt = $this->db->query("SELECT * FROM languages WHERE is_default = 1 LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Return default English if languages table doesn't exist yet
            return ['code' => 'en', 'name' => 'English', 'native_name' => 'English'];
        }
    }
    
    private function getCurrentLanguage() {
        return $_SESSION['current_language'] ?? $this->getDefaultLanguage()['code'] ?? 'en';
    }
    
    private function setCurrentLanguage($languageCode) {
        $_SESSION['current_language'] = $languageCode;
    }
    
    private function hasMediaLinking($tableName) {
        $structure = $this->getTableStructure($tableName);
        $mediaFields = [];
        foreach ($structure as $field) {
            if ($field['name'] === 'media_id' || $field['name'] === 'media_ids') {
                $mediaFields[] = $field['name'];
            }
        }
        return count($mediaFields) > 0 ? $mediaFields : false;
    }
    
    private function getMediaFields($tableName) {
        $stmt = $this->db->prepare("SELECT field_name, media_type FROM table_field_meta WHERE table_name = ? AND is_media_field = 1");
        $stmt->execute([$tableName]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mediaFields = [];
        foreach ($fields as $field) {
            $mediaFields[$field['field_name']] = $field['media_type'];
        }
        
        return $mediaFields;
    }
    
    private function getForeignKeyFields($tableName) {
        $stmt = $this->db->prepare("SELECT field_name, foreign_table, foreign_display FROM table_field_meta WHERE table_name = ? AND is_foreign_key = 1");
        $stmt->execute([$tableName]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $foreignFields = [];
        foreach ($fields as $field) {
            $foreignFields[$field['field_name']] = [
                'table' => $field['foreign_table'],
                'display' => $field['foreign_display']
            ];
        }
        
        return $foreignFields;
    }
    
    private function getForeignTableRecords($tableName) {
        // Get records from foreign table for dropdown
        try {
            $stmt = $this->db->query("SELECT * FROM `{$tableName}` ORDER BY id");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    private function getForeignRecordById($tableName, $id) {
        if (!$tableName || !$id) return null;
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    private function getDisplayValueForRecord($record, $displayColumn = null) {
        // Use specified display column if provided
        if ($displayColumn && isset($record[$displayColumn]) && !empty($record[$displayColumn])) {
            return $record[$displayColumn];
        }
        
        // Try common field names for display
        $displayFields = ['name', 'title', 'label', 'description'];
        foreach ($displayFields as $field) {
            if (isset($record[$field]) && !empty($record[$field])) {
                return $record[$field];
            }
        }
        // Fallback to ID
        return "Record #{$record['id']}";
    }
    
    private function getAllMedia() {
        $stmt = $this->db->query("SELECT * FROM media ORDER BY uploaded_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTotalMediaCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM media");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTableRecordCount($tableName) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM `{$tableName}`");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Handle REST API requests for external consumption
     */
    private function handleAPI() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $endpoint = $_GET['api'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($endpoint) {
                case 'tables':
                    $this->apiGetTables();
                    break;
                case 'records':
                    $this->apiGetRecords();
                    break;
                case 'record':
                    $this->apiGetSingleRecord();
                    break;
                case 'media':
                    $this->apiGetMedia();
                    break;
                case 'search':
                    $this->apiSearchRecords();
                    break;
                case 'languages':
                    $this->apiGetLanguages();
                    break;
                case 'translations':
                    $this->apiGetTranslations();
                    break;
                default:
                    $this->apiError('Invalid endpoint', 404);
            }
        } catch (Exception $e) {
            $this->apiError('Server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function apiGetTables() {
        $tables = $this->getUserTables();
        $result = [];
        
        foreach ($tables as $table) {
            $structure = $this->getTableStructure($table);
            $count = $this->getTableRecordCount($table);
            
            $result[] = [
                'name' => $table,
                'record_count' => $count,
                'fields' => array_map(function($field) {
                    return [
                        'name' => $field['name'],
                        'type' => $field['type']
                    ];
                }, $structure)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'meta' => [
                'total_tables' => count($result),
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiGetRecords() {
        $table = $_GET['table'] ?? '';
        $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        $orderBy = $_GET['order_by'] ?? 'id';
        $orderDir = strtoupper($_GET['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        if (!in_array($table, $this->getUserTables())) {
            $this->apiError('Table not found', 404);
            return;
        }
        
        // Get total count
        $totalStmt = $this->db->prepare("SELECT COUNT(*) as count FROM `{$table}`");
        $totalStmt->execute();
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get records with pagination
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` ORDER BY `{$orderBy}` {$orderDir} LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process media, foreign key fields, and translations
        $mediaFields = $this->getMediaFields($table);
        $foreignFields = $this->getForeignKeyFields($table);
        $languageCode = $_GET['lang'] ?? '';
        
        foreach ($records as &$record) {
            // Expand media fields
            foreach ($mediaFields as $fieldName => $mediaType) {
                if (isset($record[$fieldName]) && $record[$fieldName]) {
                    if ($mediaType === 'single') {
                        $media = $this->getMediaById($record[$fieldName]);
                        $record[$fieldName . '_media'] = $media;
                    } else {
                        $mediaIds = json_decode($record[$fieldName], true) ?: [];
                        $mediaFiles = [];
                        foreach ($mediaIds as $mediaId) {
                            $media = $this->getMediaById($mediaId);
                            if ($media) $mediaFiles[] = $media;
                        }
                        $record[$fieldName . '_media'] = $mediaFiles;
                    }
                }
            }
            
            // Expand foreign key fields
            foreach ($foreignFields as $fieldName => $foreignInfo) {
                if (isset($record[$fieldName]) && $record[$fieldName]) {
                    $foreignRecord = $this->getForeignRecordById($foreignInfo['table'], $record[$fieldName]);
                    $record[$fieldName . '_data'] = $foreignRecord;
                }
            }
            
            // Add translations if language specified
            if ($languageCode) {
                $translationStmt = $this->db->prepare("
                    SELECT field_name, translated_value 
                    FROM content_translations 
                    WHERE table_name = ? AND record_id = ? AND language_code = ?
                ");
                $translationStmt->execute([$table, $record['id'], $languageCode]);
                $translations = $translationStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $record['translations'] = [];
                foreach ($translations as $translation) {
                    $record['translations'][$translation['field_name']] = $translation['translated_value'];
                }
                $record['language'] = $languageCode;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $records,
            'meta' => [
                'table' => $table,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiGetSingleRecord() {
        $table = $_GET['table'] ?? '';
        $id = $_GET['id'] ?? '';
        
        if (!in_array($table, $this->getUserTables()) || !$id) {
            $this->apiError('Table or ID not found', 404);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            $this->apiError('Record not found', 404);
            return;
        }
        
        // Expand media and foreign key fields (same as above)
        $mediaFields = $this->getMediaFields($table);
        $foreignFields = $this->getForeignKeyFields($table);
        
        foreach ($mediaFields as $fieldName => $mediaType) {
            if (isset($record[$fieldName]) && $record[$fieldName]) {
                if ($mediaType === 'single') {
                    $media = $this->getMediaById($record[$fieldName]);
                    $record[$fieldName . '_media'] = $media;
                } else {
                    $mediaIds = json_decode($record[$fieldName], true) ?: [];
                    $mediaFiles = [];
                    foreach ($mediaIds as $mediaId) {
                        $media = $this->getMediaById($mediaId);
                        if ($media) $mediaFiles[] = $media;
                    }
                    $record[$fieldName . '_media'] = $mediaFiles;
                }
            }
        }
        
        foreach ($foreignFields as $fieldName => $foreignInfo) {
            if (isset($record[$fieldName]) && $record[$fieldName]) {
                $foreignRecord = $this->getForeignRecordById($foreignInfo['table'], $record[$fieldName]);
                $record[$fieldName . '_data'] = $foreignRecord;
            }
        }
        
        // Add translations if language specified
        $languageCode = $_GET['lang'] ?? '';
        if ($languageCode) {
            $translationStmt = $this->db->prepare("
                SELECT field_name, translated_value 
                FROM content_translations 
                WHERE table_name = ? AND record_id = ? AND language_code = ?
            ");
            $translationStmt->execute([$table, $record['id'], $languageCode]);
            $translations = $translationStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $record['translations'] = [];
            foreach ($translations as $translation) {
                $record['translations'][$translation['field_name']] = $translation['translated_value'];
            }
            $record['language'] = $languageCode;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $record,
            'meta' => [
                'table' => $table,
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiGetMedia() {
        $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        $tag = $_GET['tag'] ?? '';
        
        $sql = "SELECT * FROM media";
        $params = [];
        
        if ($tag) {
            $sql .= " WHERE tags LIKE ?";
            $params[] = "%{$tag}%";
        }
        
        $sql .= " ORDER BY uploaded_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as count FROM media";
        if ($tag) {
            $countSql .= " WHERE tags LIKE ?";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(["%{$tag}%"]);
        } else {
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute();
        }
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'data' => $media,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiSearchRecords() {
        $table = $_GET['table'] ?? '';
        $query = $_GET['q'] ?? '';
        $field = $_GET['field'] ?? '';
        $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
        
        if (!in_array($table, $this->getUserTables()) || !$query) {
            $this->apiError('Invalid search parameters', 400);
            return;
        }
        
        $structure = $this->getTableStructure($table);
        $searchFields = [];
        
        if ($field) {
            // Search specific field
            $searchFields = [$field];
        } else {
            // Search all text fields
            foreach ($structure as $fieldInfo) {
                if (in_array(strtolower($fieldInfo['type']), ['text', 'varchar'])) {
                    $searchFields[] = $fieldInfo['name'];
                }
            }
        }
        
        if (empty($searchFields)) {
            $this->apiError('No searchable fields found', 400);
            return;
        }
        
        $conditions = [];
        $params = [];
        foreach ($searchFields as $searchField) {
            $conditions[] = "`{$searchField}` LIKE ?";
            $params[] = "%{$query}%";
        }
        
        $sql = "SELECT * FROM `{$table}` WHERE " . implode(' OR ', $conditions) . " LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $records,
            'meta' => [
                'table' => $table,
                'query' => $query,
                'fields_searched' => $searchFields,
                'results_count' => count($records),
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    private function apiGetLanguages() {
        $languages = $this->getActiveLanguages();
        $defaultLang = $this->getDefaultLanguage();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'languages' => $languages,
                'default_language' => $defaultLang['code'] ?? 'en',
                'current_language' => $this->getCurrentLanguage()
            ],
            'meta' => [
                'total_languages' => count($languages),
                'timestamp' => date('c')
            ]
        ]);
        exit;
    }
    
    private function apiGetTranslations() {
        $table = $_GET['table'] ?? '';
        $recordId = $_GET['record_id'] ?? '';
        $languageCode = $_GET['lang'] ?? '';
        
        if (!$table || !$recordId) {
            $this->apiError('Table and record_id are required', 400);
            return;
        }
        
        if (!in_array($table, $this->getUserTables())) {
            $this->apiError('Table not found', 404);
            return;
        }
        
        // Get all translations for this record
        if ($languageCode) {
            // Get translations for specific language
            $stmt = $this->db->prepare("
                SELECT field_name, translated_value, updated_at 
                FROM content_translations 
                WHERE table_name = ? AND record_id = ? AND language_code = ?
            ");
            $stmt->execute([$table, $recordId, $languageCode]);
            $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($translations as $translation) {
                $result[$translation['field_name']] = [
                    'value' => $translation['translated_value'],
                    'updated_at' => $translation['updated_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'translations' => $result,
                    'language' => $languageCode,
                    'table' => $table,
                    'record_id' => $recordId
                ],
                'meta' => [
                    'total_fields' => count($result),
                    'timestamp' => date('c')
                ]
            ]);
        } else {
            // Get translations for all languages
            $stmt = $this->db->prepare("
                SELECT field_name, language_code, translated_value, updated_at 
                FROM content_translations 
                WHERE table_name = ? AND record_id = ?
                ORDER BY language_code, field_name
            ");
            $stmt->execute([$table, $recordId]);
            $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($translations as $translation) {
                $lang = $translation['language_code'];
                $field = $translation['field_name'];
                
                if (!isset($result[$lang])) {
                    $result[$lang] = [];
                }
                
                $result[$lang][$field] = [
                    'value' => $translation['translated_value'],
                    'updated_at' => $translation['updated_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'translations' => $result,
                    'table' => $table,
                    'record_id' => $recordId
                ],
                'meta' => [
                    'total_languages' => count($result),
                    'timestamp' => date('c')
                ]
            ]);
        }
        exit;
    }
    

    private function getTableIcon($tableName) {
        // Return appropriate icons based on table name
        $tableName = strtolower($tableName);
        
        if (strpos($tableName, 'article') !== false || strpos($tableName, 'post') !== false || strpos($tableName, 'blog') !== false) {
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>';
        } elseif (strpos($tableName, 'categor') !== false || strpos($tableName, 'tag') !== false) {
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>';
        } elseif (strpos($tableName, 'user') !== false || strpos($tableName, 'profile') !== false) {
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>';
        } elseif (strpos($tableName, 'product') !== false || strpos($tableName, 'item') !== false) {
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>';
        } elseif (strpos($tableName, 'event') !== false || strpos($tableName, 'calendar') !== false) {
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>';
        } else {
            // Default table icon
            return '<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>';
        }
    }
    
    private function renderSelectedMediaPreview($media, $showName = true) {
        $isImage = in_array(strtolower(pathinfo($media['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        ?>
        <div class="<?= $showName ? 'flex items-center space-x-3 p-3 bg-gray-50 rounded-lg border' : 'w-16 h-16' ?>">
            <div class="<?= $showName ? 'w-12 h-12' : 'w-full h-full' ?> bg-gray-100 rounded overflow-hidden flex-shrink-0">
                <?php if ($isImage): ?>
                    <img src="<?= htmlspecialchars($media['path']) ?>" alt="<?= htmlspecialchars($media['original_filename']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($showName): ?>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($media['original_filename']) ?></p>
                    <p class="text-xs text-gray-500"><?= number_format($media['file_size'] / 1024, 1) ?> KB<?= $media['tags'] ? ' • ' . htmlspecialchars($media['tags']) : '' ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function getMediaJson() {
        header('Content-Type: application/json');
        
        try {
            $stmt = $this->db->query("SELECT * FROM media ORDER BY uploaded_at DESC");
            $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unique tags
            $tags = [];
            foreach ($media as $file) {
                if ($file['tags']) {
                    $fileTags = array_map('trim', explode(',', $file['tags']));
                    $tags = array_merge($tags, $fileTags);
                }
            }
            $tags = array_unique($tags);
            sort($tags);
            
            echo json_encode([
                'media' => $media,
                'tags' => $tags,
                'success' => true
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'error' => $e->getMessage(),
                'media' => [],
                'tags' => []
            ]);
        }
        exit;
    }
    
    private function getMediaPreview() {
        $ids = explode(',', $_GET['ids'] ?? '');
        $type = $_GET['type'] ?? 'single';
        
        if (empty($ids)) {
            echo '';
            exit;
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $this->db->prepare("SELECT * FROM media WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $mediaFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($mediaFiles)) {
            echo '';
            exit;
        }
        
        if ($type === 'single' && count($mediaFiles) > 0) {
            $this->renderSelectedMediaPreview($mediaFiles[0], true);
        } elseif ($type === 'multiple') {
            ?>
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700"><?= count($mediaFiles) ?> file(s) selected:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($mediaFiles, 0, 5) as $media): ?>
                        <?php $this->renderSelectedMediaPreview($media, false); ?>
                    <?php endforeach; ?>
                    <?php if (count($mediaFiles) > 5): ?>
                        <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center border">
                            <span class="text-xs text-gray-600">+<?= count($mediaFiles) - 5 ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        exit;
    }
    
    private function getMediaById($id) {
        if (!$id) return null;
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM media WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Handle CRUD operations
     */
    private function handleCRUD() {
        if (!$this->isLoggedIn()) {
            return ['error' => 'Access denied'];
        }
        
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $table = $_POST['table'] ?? $_GET['table'] ?? '';
        
        // Handle media AJAX requests first (no table validation needed)
        switch ($action) {
            case 'get_media_json':
                $this->getMediaJson();
                return null;
            case 'get_media_preview':
                $this->getMediaPreview();
                return null;
        }
        
        if ($table && !in_array($table, array_merge($this->getUserTables(), ['media']))) {
            return ['error' => 'Invalid table'];
        }
        
        switch ($action) {
            case 'create_table':
                return $this->handleCreateTable($_POST);
            case 'create_record':
                return $this->createRecord($table, $_POST);
            case 'update_record':
                return $this->updateRecord($table, $_POST);
            case 'delete_record':
                return $this->deleteRecord($table, $_POST['id']);
            case 'upload_media':
                return $this->uploadMedia();
            case 'add_table_field':
                return $this->addTableField($table, $_POST);
            case 'switch_language':
                return $this->switchLanguage($_POST['language_code']);
            case 'save_translation':
                return $this->saveTranslationBatch($_POST);
            case 'toggle_language':
                return $this->toggleLanguage($_POST);
            case 'set_default_language':
                return $this->setDefaultLanguage($_POST['language_code']);
        }
        
        return null;
    }
    
    private function createRecord($table, $data) {
        unset($data['action'], $data['table']);
        
        // Handle media fields
        $mediaFields = $this->getMediaFields($table);
        foreach ($mediaFields as $fieldName => $mediaType) {
            if (isset($data[$fieldName])) {
                if ($mediaType === 'multiple' && is_array($data[$fieldName])) {
                    $data[$fieldName] = json_encode($data[$fieldName]);
                }
            }
        }
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute(array_values($data))) {
            return ['success' => 'Record created successfully!'];
        } else {
            return ['error' => 'Failed to create record'];
        }
    }
    
    private function updateRecord($table, $data) {
        $id = $data['id'];
        unset($data['action'], $data['table'], $data['id']);
        
        // Handle media fields
        $mediaFields = $this->getMediaFields($table);
        foreach ($mediaFields as $fieldName => $mediaType) {
            if (isset($data[$fieldName])) {
                if ($mediaType === 'multiple' && is_array($data[$fieldName])) {
                    $data[$fieldName] = json_encode($data[$fieldName]);
                }
            }
        }
        
        $setPairs = [];
        foreach (array_keys($data) as $field) {
            $setPairs[] = "`{$field}` = ?";
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setPairs) . " WHERE id = ?";
        $values = array_merge(array_values($data), [$id]);
        
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($values)) {
            return ['success' => 'Record updated successfully!'];
        } else {
            return ['error' => 'Failed to update record'];
        }
    }
    
    private function deleteRecord($table, $id) {
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            return ['success' => 'Record deleted successfully!'];
        } else {
            return ['error' => 'Failed to delete record'];
        }
    }
    
    private function uploadMedia() {
        if (!isset($_FILES['media_file'])) {
            return ['error' => 'No file uploaded'];
        }
        
        $file = $_FILES['media_file'];
        $originalFilename = basename($file['name']);
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $safeFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-.]/', '', $originalFilename);
        $targetPath = MEDIA_DIR . '/' . $safeFilename;
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['error' => 'File type not allowed. Please upload images, PDFs, or text files.'];
        }
        
        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['error' => 'File too large. Maximum size is 5MB.'];
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
            $stmt = $this->db->prepare("INSERT INTO media (filename, original_filename, path, mime_type, file_size, tags) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$safeFilename, $originalFilename, $targetPath, $file['type'], $file['size'], $tags]);
            
            return ['success' => 'File uploaded successfully!'];
        } else {
            return ['error' => 'Failed to upload file'];
        }
    }
    
    private function getRecords($table, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Render the application
     */
    public function render() {
        $message = null;
        
        // Handle API requests (public access)
        if (isset($_GET['api'])) {
            $this->handleAPI();
            return;
        }
        
        // Handle AJAX requests for media (both GET and POST)
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        if (in_array($action, ['get_media_json', 'get_media_preview']) && $this->isLoggedIn()) {
            $this->handleCRUD();
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->isSetup) {
                $message = $this->handleSetup();
                $this->isSetup = $this->checkSetupComplete();
            } else {
                $authResult = $this->handleAuth();
                if ($authResult) {
                    $message = $authResult;
                } else {
                    $message = $this->handleCRUD();
                }
            }
        }
        
        if (!$this->isSetup) {
            $this->renderSetupWizard($message);
        } elseif (!$this->isLoggedIn()) {
            $this->renderLoginForm($message);
        } else {
            $this->renderDashboard($message);
        }
    }
    
    /**
     * Render enhanced setup wizard
     */
    private function renderSetupWizard($message) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CMS Setup Wizard</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                'sans': ['Ubuntu', 'system-ui', 'sans-serif'],
                            },
                            colors: {
                                primary: '#3B82F6',
                                secondary: '#8B5CF6'
                            }
                        }
                    }
                }
            </script>
        </head>
        <body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen font-sans">
            <div class="container mx-auto px-4 py-8 max-w-4xl">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-gray-800 mb-4">CMS Setup Wizard</h1>
                    <p class="text-gray-600 text-lg">Let's set up your new content management system step by step!</p>
                </div>
                
                <!-- Progress Indicator -->
                <div class="mb-8">
                    <div class="flex items-center justify-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <div id="step1-indicator" class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-medium">1</div>
                            <span id="step1-label" class="text-sm font-medium text-primary">Admin Account</span>
                        </div>
                        <div id="progress1" class="w-16 h-1 bg-gray-200 rounded-full">
                            <div class="h-1 bg-gray-300 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div id="step2-indicator" class="w-8 h-8 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">2</div>
                            <span id="step2-label" class="text-sm font-medium text-gray-500">Content Structure</span>
                        </div>
                        <div id="progress2" class="w-16 h-1 bg-gray-200 rounded-full">
                            <div class="h-1 bg-gray-300 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div id="step3-indicator" class="w-8 h-8 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">3</div>
                            <span id="step3-label" class="text-sm font-medium text-gray-500">Review & Complete</span>
                        </div>
                    </div>
                </div>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?= isset($message['error']) ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <?php if (isset($message['error'])): ?>
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                <?php else: ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                <?php endif; ?>
                            </svg>
                            <?= htmlspecialchars($message['error'] ?? $message['success']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <form method="POST" id="setupForm">
                        <input type="hidden" name="action" value="setup">
                        <input type="hidden" name="custom_tables" id="custom_tables">
                        
                        <!-- Step 1: Admin User -->
                        <div id="step1" class="wizard-step p-8">
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Create Your Admin Account</h2>
                                <p class="text-gray-600">This will be your main administrator account to access and manage the CMS.</p>
                            </div>
                            
                            <div class="space-y-6">
                                <div>
                                    <label for="admin_username" class="block text-sm font-medium text-gray-700 mb-2">Administrator Username</label>
                                    <input type="text" id="admin_username" name="admin_username" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                           placeholder="Enter your admin username">
                                    <p class="text-xs text-gray-500 mt-1">This will be used to log into your CMS</p>
                                </div>
                                
                                <div>
                                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">Administrator Password</label>
                                    <input type="password" id="admin_password" name="admin_password" required minlength="6"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                           placeholder="Enter a secure password">
                                    <p class="text-xs text-gray-500 mt-1">Must be at least 6 characters long</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Content Structure -->
                        <div id="step2" class="wizard-step p-8 hidden">
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Define Your Content Structure</h2>
                                <p class="text-gray-600">Create custom tables for your content. You can skip this step and add tables later if you prefer.</p>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                <h4 class="font-semibold text-blue-800 mb-2">Content Table Examples</h4>
                                <p class="text-blue-700 text-sm">Here are some common content types you might want to manage:</p>
                                <ul class="text-blue-700 text-sm mt-2 list-disc list-inside">
                                    <li><strong>Blog Posts:</strong> title, content, author, publication date</li>
                                    <li><strong>Products:</strong> name, price, description, category, images</li>
                                    <li><strong>Events:</strong> title, date, location, description</li>
                                    <li><strong>Team Members:</strong> name, position, bio, photo</li>
                                </ul>
                            </div>
                            
                            <div id="tables-container">
                                <div class="table-builder border border-gray-200 rounded-lg p-4 mb-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-medium text-gray-800">Content Table 1</h3>
                                        <button type="button" onclick="removeTable(this.closest('.table-builder'))" 
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">Remove</button>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Table Name</label>
                                            <input type="text" class="table-name w-full px-3 py-2 border border-gray-300 rounded-lg"
                                                   placeholder="e.g., articles, products, events">
                                            <p class="text-xs text-gray-500 mt-1">Use field types "Media (Single File)" or "Media (Multiple Files)" for media fields</p>
                                        </div>
                                    </div>
                                    
                                    <div class="fields-container space-y-3">
                                        <div class="field-row border border-gray-200 rounded-lg p-3 space-y-3">
                                            <div class="grid grid-cols-12 gap-2 items-end">
                                                <div class="col-span-5">
                                                    <label class="block text-xs text-gray-600 mb-1">Field Name</label>
                                                    <input type="text" placeholder="e.g., title, content" class="field-name w-full px-3 py-2 border border-gray-300 rounded">
                                                </div>
                                                <div class="col-span-4">
                                                    <label class="block text-xs text-gray-600 mb-1">Field Type</label>
                                                    <select class="field-type w-full px-3 py-2 border border-gray-300 rounded" onchange="toggleForeignKeyConfig(this)">
                                                        <option value="text">Text (short)</option>
                                                        <option value="textarea">Text (long)</option>
                                                        <option value="number">Number</option>
                                                        <option value="decimal">Decimal</option>
                                                        <option value="date">Date</option>
                                                        <option value="datetime">Date & Time</option>
                                                        <option value="boolean">Yes/No</option>
                                                        <option value="media_single">Media (Single File)</option>
                                                        <option value="media_multiple">Media (Multiple Files)</option>
                                                        <option value="foreign_key">Foreign Key (Link to Table)</option>
                                                    </select>
                                                </div>
                                                <div class="col-span-3">
                                                    <button type="button" onclick="removeField(this.closest('.field-row'))"
                                                            class="w-full px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">Remove</button>
                                                </div>
                                            </div>
                                            
                                            <!-- Foreign Key Configuration -->
                                            <div class="foreign-key-config hidden bg-blue-50 border border-blue-200 rounded p-3">
                                                <h4 class="text-sm font-medium text-blue-800 mb-2">Foreign Key Configuration</h4>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs text-gray-600 mb-1">Target Table</label>
                                                        <input type="text" placeholder="e.g., categories" class="foreign-table w-full px-3 py-2 border border-gray-300 rounded text-sm">
                                                        <p class="text-xs text-gray-500 mt-1">Table to link to</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs text-gray-600 mb-1">Display Column</label>
                                                        <input type="text" placeholder="e.g., name" class="foreign-display w-full px-3 py-2 border border-gray-300 rounded text-sm">
                                                        <p class="text-xs text-gray-500 mt-1">Column to show in dropdown</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" onclick="addField(this.closest('.table-builder'))"
                                            class="mt-3 px-4 py-2 bg-green-100 text-green-700 rounded hover:bg-green-200 text-sm font-medium">Add Field</button>
                                </div>
                            </div>
                            
                            <button type="button" onclick="addTable()"
                                    class="px-6 py-3 bg-secondary text-white rounded-lg hover:bg-purple-700 font-medium">Add Another Table</button>
                        </div>
                        
                        <!-- Step 3: Review & Complete -->
                        <div id="step3" class="wizard-step p-8 hidden">
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Review Your Setup</h2>
                                <p class="text-gray-600">Please review your configuration before completing the setup.</p>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="font-semibold text-gray-800 mb-2">Admin Account</h3>
                                    <p id="review-username" class="text-gray-600"></p>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="font-semibold text-gray-800 mb-2">Content Tables</h3>
                                    <div id="review-tables" class="text-gray-600"></div>
                                </div>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h4 class="font-semibold text-blue-800 mb-2">What happens next?</h4>
                                    <ul class="text-blue-700 text-sm list-disc list-inside space-y-1">
                                        <li>Your CMS database will be created</li>
                                        <li>Your admin account will be set up</li>
                                        <li>Your custom content tables will be created</li>
                                        <li>You'll be redirected to the login page</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navigation Buttons -->
                        <div class="border-t border-gray-200 px-8 py-6 bg-gray-50 flex justify-between">
                            <button type="button" id="prevBtn" onclick="changeStep(-1)" 
                                    class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium hidden">
                                Previous
                            </button>
                            <div class="flex-1"></div>
                            <button type="button" id="nextBtn" onclick="changeStep(1)" 
                                    class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium">
                                Next Step
                            </button>
                            <button type="submit" id="submitBtn" onclick="prepareSubmission()" 
                                    class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-lg hidden">
                                Complete Setup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                let currentStep = 1;
                let tableCounter = 1;
                
                function changeStep(direction) {
                    if (direction === 1) {
                        // Moving forward - validate current step
                        if (currentStep === 1) {
                            const username = document.getElementById('admin_username').value.trim();
                            const password = document.getElementById('admin_password').value.trim();
                            
                            if (!username || !password) {
                                alert('Please fill in both username and password');
                                return;
                            }
                            
                            if (password.length < 6) {
                                alert('Password must be at least 6 characters long');
                                return;
                            }
                        }
                        
                        if (currentStep === 2) {
                            // Update review section
                            updateReview();
                        }
                    }
                    
                    // Hide current step
                    document.getElementById(`step${currentStep}`).classList.add('hidden');
                    
                    // Update current step
                    currentStep += direction;
                    
                    // Show new step
                    document.getElementById(`step${currentStep}`).classList.remove('hidden');
                    
                    // Update progress indicators
                    updateProgressIndicators();
                    
                    // Update navigation buttons
                    updateNavigationButtons();
                }
                
                function updateProgressIndicators() {
                    // Reset all indicators
                    for (let i = 1; i <= 3; i++) {
                        const indicator = document.getElementById(`step${i}-indicator`);
                        const label = document.getElementById(`step${i}-label`);
                        const progress = document.getElementById(`progress${i}`);
                        
                        if (i < currentStep) {
                            // Completed step
                            indicator.className = 'w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-sm font-medium';
                            indicator.innerHTML = '✓';
                            label.className = 'text-sm font-medium text-green-600';
                            if (progress) progress.querySelector('div').style.width = '100%';
                        } else if (i === currentStep) {
                            // Current step
                            indicator.className = 'w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-medium';
                            indicator.innerHTML = i;
                            label.className = 'text-sm font-medium text-primary';
                            if (progress && i < 3) progress.querySelector('div').style.width = '0%';
                        } else {
                            // Future step
                            indicator.className = 'w-8 h-8 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium';
                            indicator.innerHTML = i;
                            label.className = 'text-sm font-medium text-gray-500';
                            if (progress) progress.querySelector('div').style.width = '0%';
                        }
                    }
                }
                
                function updateNavigationButtons() {
                    const prevBtn = document.getElementById('prevBtn');
                    const nextBtn = document.getElementById('nextBtn');
                    const submitBtn = document.getElementById('submitBtn');
                    
                    // Previous button
                    if (currentStep === 1) {
                        prevBtn.classList.add('hidden');
                    } else {
                        prevBtn.classList.remove('hidden');
                    }
                    
                    // Next/Submit buttons
                    if (currentStep === 3) {
                        nextBtn.classList.add('hidden');
                        submitBtn.classList.remove('hidden');
                    } else {
                        nextBtn.classList.remove('hidden');
                        submitBtn.classList.add('hidden');
                    }
                }
                
                function updateReview() {
                    const username = document.getElementById('admin_username').value;
                    document.getElementById('review-username').textContent = `Username: ${username}`;
                    
                    const tableBuilders = document.querySelectorAll('.table-builder');
                    const reviewTables = document.getElementById('review-tables');
                    
                    if (tableBuilders.length === 0 || !hasValidTables()) {
                        reviewTables.innerHTML = '<p class="text-gray-500 italic">No content tables configured (you can add them later)</p>';
                    } else {
                        let tablesHtml = '<ul class="space-y-2">';
                        tableBuilders.forEach(builder => {
                            const tableName = builder.querySelector('.table-name').value.trim();
                            if (tableName) {
                                const fieldCount = builder.querySelectorAll('.field-row .field-name').length;
                                const validFields = Array.from(builder.querySelectorAll('.field-row .field-name'))
                                    .filter(input => input.value.trim()).length;
                                
                                if (validFields > 0) {
                                    tablesHtml += `<li><strong>${tableName}:</strong> ${validFields} field(s)</li>`;
                                }
                            }
                        });
                        tablesHtml += '</ul>';
                        reviewTables.innerHTML = tablesHtml;
                    }
                }
                
                function hasValidTables() {
                    const tableBuilders = document.querySelectorAll('.table-builder');
                    for (let builder of tableBuilders) {
                        const tableName = builder.querySelector('.table-name').value.trim();
                        if (tableName) {
                            const validFields = Array.from(builder.querySelectorAll('.field-row .field-name'))
                                .filter(input => input.value.trim()).length;
                            if (validFields > 0) return true;
                        }
                    }
                    return false;
                }
                
                function addField(tableElement) {
                    const fieldsContainer = tableElement.querySelector('.fields-container');
                    const fieldRow = document.createElement('div');
                    fieldRow.className = 'field-row border border-gray-200 rounded-lg p-3 space-y-3';
                    fieldRow.innerHTML = `
                        <div class="grid grid-cols-12 gap-2 items-end">
                            <div class="col-span-5">
                                <label class="block text-xs text-gray-600 mb-1">Field Name</label>
                                <input type="text" placeholder="e.g., title, content" class="field-name w-full px-3 py-2 border border-gray-300 rounded">
                            </div>
                            <div class="col-span-4">
                                <label class="block text-xs text-gray-600 mb-1">Field Type</label>
                                <select class="field-type w-full px-3 py-2 border border-gray-300 rounded" onchange="toggleForeignKeyConfig(this)">
                                    <option value="text">Text (short)</option>
                                    <option value="textarea">Text (long)</option>
                                    <option value="number">Number</option>
                                    <option value="decimal">Decimal</option>
                                    <option value="date">Date</option>
                                    <option value="datetime">Date & Time</option>
                                    <option value="boolean">Yes/No</option>
                                    <option value="media_single">Media (Single File)</option>
                                    <option value="media_multiple">Media (Multiple Files)</option>
                                    <option value="foreign_key">Foreign Key (Link to Table)</option>
                                </select>
                            </div>
                            <div class="col-span-3">
                                <button type="button" onclick="removeField(this.closest('.field-row'))"
                                        class="w-full px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">Remove</button>
                            </div>
                        </div>
                        
                        <!-- Foreign Key Configuration -->
                        <div class="foreign-key-config hidden bg-blue-50 border border-blue-200 rounded p-3">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Foreign Key Configuration</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Target Table</label>
                                    <input type="text" placeholder="e.g., categories" class="foreign-table w-full px-3 py-2 border border-gray-300 rounded text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Table to link to</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Display Column</label>
                                    <input type="text" placeholder="e.g., name" class="foreign-display w-full px-3 py-2 border border-gray-300 rounded text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Column to show in dropdown</p>
                                </div>
                            </div>
                        </div>
                    `;
                    fieldsContainer.appendChild(fieldRow);
                }
                
                function removeField(fieldRow) {
                    fieldRow.remove();
                }
                
                function toggleForeignKeyConfig(selectElement) {
                    const fieldRow = selectElement.closest('.field-row');
                    const foreignKeyConfig = fieldRow.querySelector('.foreign-key-config');
                    
                    if (selectElement.value === 'foreign_key') {
                        foreignKeyConfig.classList.remove('hidden');
                    } else {
                        foreignKeyConfig.classList.add('hidden');
                    }
                }
                
                function addTable() {
                    tableCounter++;
                    const container = document.getElementById('tables-container');
                    const tableBuilder = document.createElement('div');
                    tableBuilder.className = 'table-builder border border-gray-200 rounded-lg p-4 mb-4';
                    tableBuilder.innerHTML = `
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-800">Content Table ${tableCounter}</h3>
                            <button type="button" onclick="removeTable(this.closest('.table-builder'))" 
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">Remove</button>
                        </div>
                        
                        <div class="mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Table Name</label>
                                <input type="text" class="table-name w-full px-3 py-2 border border-gray-300 rounded-lg"
                                       placeholder="e.g., articles, products, events">
                                <p class="text-xs text-gray-500 mt-1">Use field types "Media (Single File)" or "Media (Multiple Files)" for media fields</p>
                            </div>
                        </div>
                        
                        <div class="fields-container space-y-3">
                            <div class="field-row grid grid-cols-12 gap-2 items-end">
                                <div class="col-span-5">
                                    <label class="block text-xs text-gray-600 mb-1">Field Name</label>
                                    <input type="text" placeholder="e.g., title, content" class="field-name w-full px-3 py-2 border border-gray-300 rounded">
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-xs text-gray-600 mb-1">Field Type</label>
                                    <select class="field-type w-full px-3 py-2 border border-gray-300 rounded">
                                        <option value="text">Text (short)</option>
                                        <option value="textarea">Text (long)</option>
                                        <option value="number">Number</option>
                                        <option value="decimal">Decimal</option>
                                        <option value="date">Date</option>
                                        <option value="datetime">Date & Time</option>
                                        <option value="boolean">Yes/No</option>
                                        <option value="media_single">Media (Single File)</option>
                                        <option value="media_multiple">Media (Multiple Files)</option>
                                        <option value="foreign_key">Foreign Key (Link to Table)</option>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <button type="button" onclick="removeField(this.closest('.field-row'))"
                                            class="w-full px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">Remove</button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="addField(this.closest('.table-builder'))"
                                class="mt-3 px-4 py-2 bg-green-100 text-green-700 rounded hover:bg-green-200 text-sm font-medium">Add Field</button>
                    `;
                    container.appendChild(tableBuilder);
                }
                
                function removeTable(tableElement) {
                    tableElement.remove();
                }
                
                function prepareSubmission() {
                    const tables = {};
                    const tableBuilders = document.querySelectorAll('.table-builder');
                    
                    tableBuilders.forEach((builder, index) => {
                        const tableName = builder.querySelector('.table-name').value.trim();
                        if (tableName) {
                            const fields = [];
                            const fieldRows = builder.querySelectorAll('.field-row');
                            
                            fieldRows.forEach(row => {
                                const fieldName = row.querySelector('.field-name').value.trim();
                                const fieldType = row.querySelector('.field-type').value;
                                
                                if (fieldName) {
                                    const field = { name: fieldName, type: fieldType };
                                    
                                    // Add foreign key configuration if it's a foreign key field
                                    if (fieldType === 'foreign_key') {
                                        const foreignTable = row.querySelector('.foreign-table').value.trim();
                                        const foreignDisplay = row.querySelector('.foreign-display').value.trim();
                                        field.foreign_table = foreignTable;
                                        field.foreign_display = foreignDisplay;
                                    }
                                    
                                    fields.push(field);
                                }
                            });
                            
                            if (fields.length > 0) {
                                tables[tableName] = {
                                    fields: fields
                                };
                            }
                        }
                    });
                    
                    document.getElementById('custom_tables').value = JSON.stringify(tables);
                    return true;
                }
                
                // Initialize wizard on page load
                document.addEventListener('DOMContentLoaded', function() {
                    updateProgressIndicators();
                    updateNavigationButtons();
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render modern login form
     */
    private function renderLoginForm($message) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CMS Login</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                'sans': ['Ubuntu', 'system-ui', 'sans-serif'],
                            }
                        }
                    }
                }
            </script>
        </head>
        <body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen flex items-center justify-center font-sans">
            <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h1>
                    <p class="text-gray-600">Sign in to access your CMS</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?= isset($message['error']) ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <?php if (isset($message['error'])): ?>
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                <?php else: ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                <?php endif; ?>
                            </svg>
                            <?= htmlspecialchars($message['error'] ?? $message['success']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <input type="text" id="username" name="username" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your username">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your password">
                    </div>
                    
                    <button type="submit"
                            class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold shadow-lg transform hover:scale-105 transition-all">
                        Sign In
                    </button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render beautiful dashboard
     */
    private function renderDashboard($message) {
        $currentTable = $_GET['table'] ?? '';
        $currentAction = $_GET['action'] ?? '';
        $currentId = $_GET['id'] ?? '';
        
        $userTables = $this->getUserTables();
        $allTables = array_merge(['media'], $userTables);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CMS Dashboard</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                'sans': ['Ubuntu', 'system-ui', 'sans-serif'],
                            }
                        }
                    }
                }
            </script>
            <style>
                .sidebar-transition { transition: transform 0.3s ease-in-out; }
                @media (max-width: 768px) {
                    .sidebar-hidden { transform: translateX(-100%); }
                }
            </style>
        </head>
        <body class="bg-gray-50 font-sans">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button onclick="toggleSidebar()" class="md:hidden mr-4 p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-800">CMS Dashboard</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        
                        <span class="text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </header>
            
            <div class="flex h-screen">
                <!-- Sidebar -->
                <nav id="sidebar" class="sidebar-transition w-64 bg-white border-r border-gray-200 shadow-sm md:translate-x-0 fixed md:static z-30 h-full">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Content Tables</h3>
                        
                        <?php if (empty($userTables)): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <p class="text-blue-800 text-sm">
                                    <strong>No content tables yet!</strong><br>
                                    You can create custom tables by re-running the setup or adding them manually to your database.
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="space-y-2">
                            <a href="?" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= empty($currentTable) ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"/>
                                </svg>
                                <span>Dashboard</span>
                            </a>
                            
                            <a href="?table=media" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= $currentTable === 'media' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16l-1 10H5L4 7z"/>
                                </svg>
                                <span>Media Library</span>
                            </a>
                            
                            <a href="?page=api-docs" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= ($_GET['page'] ?? '') === 'api-docs' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                                <span>API Docs</span>
                            </a>
                            
                            <a href="?page=languages" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= ($_GET['page'] ?? '') === 'languages' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                                </svg>
                                <span>Languages</span>
                            </a>
                            
                            <a href="?page=create-table" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= ($_GET['page'] ?? '') === 'create-table' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span>Create Table</span>
                            </a>
                            
                            <?php foreach ($userTables as $table): ?>
                                <a href="?table=<?= urlencode($table) ?>" class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-100 <?= $currentTable === $table ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700' ?>">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span><?= htmlspecialchars(ucfirst($table)) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 mb-3">SYSTEM INFO</h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <div>Database: <?= DB_FILE ?></div>
                                <div>Media: <?= MEDIA_DIR ?>/</div>
                                <div>Tables: <?= count($userTables) + 2 ?></div>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Main Content -->
                <main class="flex-1 p-6 overflow-y-auto md:ml-0">
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= isset($message['error']) ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <?php if (isset($message['error'])): ?>
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    <?php else: ?>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    <?php endif; ?>
                                </svg>
                                <?= htmlspecialchars($message['error'] ?? $message['success']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    $currentPage = $_GET['page'] ?? '';
                    if ($currentPage === 'api-docs'): ?>
                        <?php $this->renderApiDocumentation($userTables); ?>
                    <?php elseif ($currentPage === 'languages'): ?>
                        <?php $this->renderLanguageManagement(); ?>
                    <?php elseif ($currentPage === 'create-table'): ?>
                        <?php $this->renderCreateTable(); ?>
                    <?php elseif ($currentAction === 'edit_table_structure' && $currentTable): ?>
                        <?php $this->renderEditTableStructure($currentTable); ?>
                    <?php elseif ($currentTable): ?>
                        <?php $this->renderTableManagement($currentTable, $currentAction, $currentId); ?>
                    <?php else: ?>
                        <?php $this->renderDashboardHome($userTables); ?>
                    <?php endif; ?>
                </main>
            </div>
            
            <!-- Mobile sidebar overlay -->
            <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden" onclick="toggleSidebar()"></div>
            
            <script>
                function toggleSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebar-overlay');
                    
                    sidebar.classList.toggle('sidebar-hidden');
                    overlay.classList.toggle('hidden');
                }
                
                function switchLanguage(languageCode) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="switch_language">
                        <input type="hidden" name="language_code" value="${languageCode}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
                
                function switchTab(languageCode) {
                    // Hide all tab contents
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.add('hidden');
                    });
                    
                    // Remove active state from all tab buttons
                    document.querySelectorAll('.tab-button').forEach(button => {
                        button.classList.remove('border-blue-500', 'text-blue-600');
                        button.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    // Show selected tab content
                    const targetTab = document.getElementById(`tab-${languageCode}`);
                    if (targetTab) {
                        targetTab.classList.remove('hidden');
                        
                        // Initialize WYSIWYG editors in this tab
                        initializeWysiwygInTab(targetTab);
                    }
                    
                    // Activate selected tab button
                    const targetButton = document.querySelector(`[data-tab="${languageCode}"]`);
                    if (targetButton) {
                        targetButton.classList.remove('border-transparent', 'text-gray-500');
                        targetButton.classList.add('border-blue-500', 'text-blue-600');
                    }
                }
                
                function initializeWysiwygInTab(tab) {
                    const editors = tab.querySelectorAll('.wysiwyg-editor');
                    editors.forEach(function(editor) {
                        // Set up the editor if not already done
                        if (!editor.hasAttribute('data-initialized')) {
                            // Format existing content
                            if (editor.innerHTML.trim()) {
                                editor.innerHTML = editor.innerHTML.replace(/\n/g, '<br>');
                                // Make sure hidden field has the same content
                                updateHiddenField(editor);
                            }
                            
                            // Show placeholder when empty
                            if (!editor.innerHTML.trim() || editor.innerHTML === '') {
                                editor.innerHTML = '<p style="color: #9ca3af; font-style: italic;">' + editor.getAttribute('data-placeholder') + '</p>';
                            }
                            
                            // Clear placeholder on focus
                            editor.addEventListener('focus', function() {
                                if (this.innerHTML.includes('font-style: italic')) {
                                    this.innerHTML = '';
                                }
                            });
                            
                            // Update hidden field on blur
                            editor.addEventListener('blur', function() {
                                updateHiddenField(this);
                                if (!this.innerHTML.trim() || this.innerHTML === '<br>' || this.innerHTML === '<div><br></div>') {
                                    this.innerHTML = '<p style="color: #9ca3af; font-style: italic;">' + this.getAttribute('data-placeholder') + '</p>';
                                }
                            });
                            
                            // Update hidden field on input
                            editor.addEventListener('input', function() {
                                updateHiddenField(this);
                            });
                            
                            // Set initial hidden field value
                            updateHiddenField(editor);
                            
                            editor.setAttribute('data-initialized', 'true');
                        }
                    });
                }
                
                function saveTranslations(event, languageCode) {
                    event.preventDefault();
                    const form = event.target;
                    
                    console.log('Saving translations for language:', languageCode);
                    
                    // Update all WYSIWYG editors before saving
                    const editors = form.querySelectorAll('.wysiwyg-editor');
                    editors.forEach(function(editor) {
                        updateHiddenField(editor);
                        console.log('Updated WYSIWYG editor:', editor.innerHTML.substring(0, 100));
                    });
                    
                    const formData = new FormData(form);
                    
                    // Debug: Log all form data
                    for (let [key, value] of formData.entries()) {
                        console.log('Form data:', key, '=', value.substring(0, 100));
                    }
                    
                    // Convert form data to individual translation saves
                    const translations = {};
                    for (let [key, value] of formData.entries()) {
                        if (key.startsWith('translations[')) {
                            const fieldName = key.replace('translations[', '').replace(']', '');
                            translations[fieldName] = value;
                            console.log('Translation found:', fieldName, '=', value.substring(0, 50));
                        }
                    }
                    
                    // Save each translation individually
                    let saved = 0;
                    const total = Object.keys(translations).length;
                    
                    console.log('Total translations to save:', total);
                    
                    if (total === 0) {
                        alert('No translations to save.');
                        return false;
                    }
                    
                    // Save all translations (not just the first one)
                    let saveIndex = 0;
                    for (const [fieldName, translatedValue] of Object.entries(translations)) {
                        console.log('Saving translation:', fieldName, translatedValue.length, 'characters');
                        
                        const saveForm = document.createElement('form');
                        saveForm.method = 'POST';
                        saveForm.innerHTML = `
                            <input type="hidden" name="action" value="save_translation">
                            <input type="hidden" name="table" value="${formData.get('table')}">
                            <input type="hidden" name="record_id" value="${formData.get('record_id')}">
                            <input type="hidden" name="field_name" value="${fieldName}">
                            <input type="hidden" name="language_code" value="${languageCode}">
                            <input type="hidden" name="translated_value" value="${encodeURIComponent(translatedValue)}">
                        `;
                        document.body.appendChild(saveForm);
                        
                        // Save the first field immediately, queue others
                        if (saveIndex === 0) {
                            saveForm.submit();
                        } else {
                            setTimeout(() => saveForm.submit(), saveIndex * 500);
                        }
                        saveIndex++;
                    }
                    
                    return false;
                }
                
                function prepareTranslationForm(form, languageCode) {
                    console.log('Preparing translation form for:', languageCode);
                    
                    // Update all WYSIWYG editors in this form
                    const editors = form.querySelectorAll('.wysiwyg-editor');
                    editors.forEach(function(editor) {
                        updateHiddenField(editor);
                        console.log('WYSIWYG content:', editor.innerHTML.substring(0, 100));
                    });
                    
                    // Let the form submit normally
                    return true;
                }
                
                function formatText(button, command) {
                    const container = button.closest('.wysiwyg-container');
                    const editor = container.querySelector('.wysiwyg-editor');
                    
                    editor.focus();
                    
                    if (command === 'createLink') {
                        const url = prompt('Enter URL:');
                        if (url) {
                            document.execCommand('createLink', false, url);
                        }
                    } else {
                        document.execCommand(command, false, null);
                    }
                    
                    updateHiddenField(editor);
                }
                
                function updateHiddenField(editor) {
                    const container = editor.closest('.wysiwyg-container');
                    const hiddenField = container.querySelector('.wysiwyg-hidden');
                    if (hiddenField) {
                        // Clean up placeholder content before saving
                        let content = editor.innerHTML;
                        if (content.includes('font-style: italic') && content.includes('Enter ')) {
                            content = ''; // It's just placeholder text
                        }
                        hiddenField.value = content;
                        console.log('Updated hidden field:', hiddenField.name, 'with content length:', content.length);
                    } else {
                        console.error('Could not find hidden field for WYSIWYG editor');
                    }
                }
                
                function clearTranslations(languageCode) {
                    if (confirm(`Are you sure you want to clear all translations for this language? This action cannot be undone.`)) {
                        const tab = document.getElementById(`tab-${languageCode}`);
                        const inputs = tab.querySelectorAll('input[name^="translations"], textarea[name^="translations"]');
                        const editors = tab.querySelectorAll('.wysiwyg-editor');
                        
                        inputs.forEach(input => {
                            input.value = '';
                        });
                        
                        editors.forEach(editor => {
                            editor.innerHTML = '';
                            updateHiddenField(editor);
                        });
                    }
                }
                
                function deleteRecord(table, id, name = '') {
                    const itemName = name ? ` "${name}"` : '';
                    if (confirm(`Are you sure you want to delete this${itemName} record? This action cannot be undone.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_record">
                            <input type="hidden" name="table" value="${table}">
                            <input type="hidden" name="id" value="${id}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            </script>
            
            <!-- Media Selector Modal -->
            <div id="mediaSelector" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-hidden">
                        <!-- Modal Header -->
                        <div class="border-b border-gray-200 p-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-semibold text-gray-900">Select Media Files</h3>
                                <button onclick="closeMediaSelector()" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Search and Filter Controls -->
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <input type="text" id="mediaSearch" placeholder="Search by filename..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           oninput="filterMedia()">
                                </div>
                                <div>
                                    <select id="mediaTagFilter" onchange="filterMedia()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">All tags</option>
                                    </select>
                                </div>
                                <div>
                                    <select id="mediaTypeFilter" onchange="filterMedia()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">All types</option>
                                        <option value="image">Images</option>
                                        <option value="document">Documents</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Media Grid -->
                        <div class="p-6 overflow-y-auto max-h-96">
                            <div id="mediaGrid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                                <!-- Media items will be loaded here via AJAX -->
                            </div>
                            <div id="mediaLoading" class="text-center py-8 text-gray-500">
                                Loading media files...
                            </div>
                            <div id="mediaEmpty" class="text-center py-8 text-gray-500 hidden">
                                No media files found.
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="border-t border-gray-200 p-6">
                            <div class="flex items-center justify-between">
                                <div id="selectedCount" class="text-sm text-gray-600">
                                    0 files selected
                                </div>
                                <div class="flex space-x-3">
                                    <button onclick="closeMediaSelector()" 
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button onclick="confirmMediaSelection()" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Select Files
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            let currentFieldName = '';
            let currentMediaType = '';
            let selectedMediaIds = [];
            let allMediaFiles = [];
            
            async function openMediaSelector(fieldName, mediaType) {
                currentFieldName = fieldName;
                currentMediaType = mediaType;
                selectedMediaIds = [];
                
                // Get current selection
                const currentValue = document.getElementById(fieldName + '_input').value;
                if (currentValue) {
                    if (mediaType === 'single') {
                        selectedMediaIds = [currentValue.toString()];
                    } else {
                        try {
                            const parsed = JSON.parse(currentValue) || [];
                            selectedMediaIds = parsed.map(id => id.toString());
                        } catch (e) {
                            selectedMediaIds = [];
                        }
                    }
                }
                
                // Load media files
                await loadMediaFiles();
                
                // Show modal
                document.getElementById('mediaSelector').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            
            function closeMediaSelector() {
                document.getElementById('mediaSelector').classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
            
            async function loadMediaFiles() {
                try {
                    const response = await fetch('?action=get_media_json');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();
                    
                    console.log('Media data received:', data);
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    allMediaFiles = data.media || [];
                    populateTagFilter(data.tags || []);
                    renderMediaGrid();
                    
                    document.getElementById('mediaLoading').style.display = 'none';
                } catch (error) {
                    console.error('Error loading media:', error);
                    document.getElementById('mediaLoading').textContent = 'Error loading media files: ' + error.message;
                }
            }
            
            function populateTagFilter(tags) {
                const tagFilter = document.getElementById('mediaTagFilter');
                tagFilter.innerHTML = '<option value="">All tags</option>';
                
                console.log('Populating tag filter with:', tags);
                
                tags.forEach(tag => {
                    const option = document.createElement('option');
                    option.value = tag;
                    option.textContent = tag;
                    tagFilter.appendChild(option);
                });
            }
            
            function renderMediaGrid() {
                const grid = document.getElementById('mediaGrid');
                const searchTerm = document.getElementById('mediaSearch').value.toLowerCase();
                const tagFilter = document.getElementById('mediaTagFilter').value;
                const typeFilter = document.getElementById('mediaTypeFilter').value;
                
                console.log('Rendering media grid with filters:', { searchTerm, tagFilter, typeFilter });
                console.log('Total media files:', allMediaFiles.length);
                
                let filteredFiles = allMediaFiles.filter(file => {
                    if (searchTerm && !file.original_filename.toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                    
                    if (tagFilter && (!file.tags || !file.tags.includes(tagFilter))) {
                        return false;
                    }
                    
                    if (typeFilter === 'image' && !file.mime_type.startsWith('image/')) {
                        return false;
                    }
                    
                    if (typeFilter === 'document' && file.mime_type.startsWith('image/')) {
                        return false;
                    }
                    
                    return true;
                });
                
                if (filteredFiles.length === 0) {
                    grid.innerHTML = '';
                    document.getElementById('mediaEmpty').classList.remove('hidden');
                    return;
                }
                
                document.getElementById('mediaEmpty').classList.add('hidden');
                
                grid.innerHTML = filteredFiles.map(file => {
                    const isSelected = selectedMediaIds.includes(file.id.toString());
                    const isImage = file.mime_type.startsWith('image/');
                    
                    return `
                        <div class="relative cursor-pointer group" onclick="toggleMediaSelection(${file.id})">
                            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden border-2 ${isSelected ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300'}">
                                ${isImage ? 
                                    `<img src="${file.path}" alt="${file.original_filename}" class="w-full h-full object-cover">` :
                                    `<div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                        </svg>
                                    </div>`
                                }
                            </div>
                            <div class="absolute top-2 right-2">
                                <div class="w-5 h-5 rounded-full ${isSelected ? 'bg-blue-600' : 'bg-white border border-gray-300'} flex items-center justify-center">
                                    ${isSelected ? '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' : ''}
                                </div>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 truncate" title="${file.original_filename}">${file.original_filename}</p>
                            ${file.tags ? `<p class="text-xs text-gray-400 truncate">${file.tags}</p>` : ''}
                        </div>
                    `;
                }).join('');
                
                updateSelectedCount();
            }
            
            function toggleMediaSelection(mediaId) {
                const mediaIdStr = mediaId.toString();
                
                console.log('Toggling selection for media ID:', mediaIdStr);
                console.log('Current selected IDs:', selectedMediaIds);
                
                if (currentMediaType === 'single') {
                    selectedMediaIds = [mediaIdStr];
                } else {
                    const index = selectedMediaIds.indexOf(mediaIdStr);
                    if (index > -1) {
                        selectedMediaIds.splice(index, 1);
                    } else {
                        selectedMediaIds.push(mediaIdStr);
                    }
                }
                
                console.log('New selected IDs:', selectedMediaIds);
                renderMediaGrid();
            }
            
            function updateSelectedCount() {
                const count = selectedMediaIds.length;
                const countText = currentMediaType === 'single' ? 
                    (count > 0 ? '1 file selected' : 'No file selected') :
                    `${count} file${count !== 1 ? 's' : ''} selected`;
                
                document.getElementById('selectedCount').textContent = countText;
            }
            
            function filterMedia() {
                renderMediaGrid();
            }
            
            function confirmMediaSelection() {
                const input = document.getElementById(currentFieldName + '_input');
                const preview = document.getElementById(currentFieldName + '_preview');
                
                if (currentMediaType === 'single') {
                    input.value = selectedMediaIds.length > 0 ? selectedMediaIds[0] : '';
                } else {
                    input.value = JSON.stringify(selectedMediaIds);
                }
                
                // Update preview
                if (selectedMediaIds.length > 0) {
                    updateMediaPreview();
                } else {
                    preview.innerHTML = '';
                }
                
                closeMediaSelector();
            }
            
            async function updateMediaPreview() {
                const preview = document.getElementById(currentFieldName + '_preview');
                
                if (selectedMediaIds.length === 0) {
                    preview.innerHTML = '';
                    return;
                }
                
                try {
                    const response = await fetch(`?action=get_media_preview&ids=${selectedMediaIds.join(',')}&type=${currentMediaType}`);
                    const html = await response.text();
                    preview.innerHTML = html;
                } catch (error) {
                    console.error('Error updating preview:', error);
                }
            }
            
            // Initialize Flatpickr for date and datetime fields
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize date fields
                flatpickr('.flatpickr-date', {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    altInput: true,
                    altFormat: 'F j, Y'
                });
                
                // Initialize datetime fields
                flatpickr('.flatpickr-datetime', {
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    allowInput: true,
                    altInput: true,
                    altFormat: 'F j, Y at H:i',
                    time_24hr: true
                });
            });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render dashboard home
     */
    private function renderDashboardHome($userTables) {
        $totalMedia = $this->db->query("SELECT COUNT(*) as count FROM media")->fetch()['count'];
        
        ?>
        <div class="space-y-6">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome to Your CMS!</h2>
                <p class="text-gray-600">Manage your content, upload media, and organize your data all in one place.</p>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?= count($userTables) ?></h3>
                            <p class="text-gray-600">Content Tables</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16l-1 10H5L4 7z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?= $totalMedia ?></h3>
                            <p class="text-gray-600">Media Files</p>
                        </div>
                    </div>
                </div>
                
                <!-- Table Statistics -->
                <?php if (!empty($userTables)): ?>
                    <?php foreach ($userTables as $table): ?>
                        <?php 
                        $recordCount = $this->getTableRecordCount($table);
                        $tableIcon = $this->getTableIcon($table);
                        ?>
                        <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-all duration-200">
                            <div class="flex items-center justify-between">
                                <!-- Left: Icon, title and count -->
                                <div class="flex items-center">
                                    <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                                        <?= $tableIcon ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars(ucfirst($table)) ?></h3>
                                        <p class="text-sm text-gray-500"><?= number_format($recordCount) ?> records</p>
                                    </div>
                                </div>
                                
                                <!-- Right: Action buttons -->
                                <div class="flex space-x-2">
                                    <a href="?table=<?= urlencode($table) ?>&action_type=create" 
                                       class="p-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                                       title="Add New <?= htmlspecialchars(ucfirst($table)) ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </a>
                                    <a href="?table=<?= urlencode($table) ?>" 
                                       class="p-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors"
                                       title="View All <?= htmlspecialchars(ucfirst($table)) ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                        </svg>
                                    </a>
                                    <a href="?action=edit_table_structure&table=<?= urlencode($table) ?>" 
                                       class="p-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
                                       title="Edit <?= htmlspecialchars(ucfirst($table)) ?> Structure">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                        <div class="text-center">
                            <div class="p-3 bg-gray-100 rounded-lg inline-block mb-4">
                                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Tables Yet</h3>
                            <p class="text-gray-600 mb-4">Create your first table to start managing content</p>
                            <a href="?setup=tables" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Create Tables
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="?table=media" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16l-1 10H5L4 7z"/>
                        </svg>
                        <div>
                            <h4 class="font-medium text-gray-800">Manage Media</h4>
                            <p class="text-sm text-gray-600">Upload and organize your files</p>
                        </div>
                    </a>
                    
                </div>
            </div>
            
            <!-- Getting Started Guide -->
            <?php if (empty($userTables)): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-blue-800 mb-4">Getting Started</h3>
                    <div class="space-y-3 text-blue-700">
                        <p><strong>You haven't created any content tables yet!</strong> Here's how to get started:</p>
                        <ol class="list-decimal list-inside space-y-2 ml-4">
                            <li>Think about what type of content you want to manage (blog posts, products, events, etc.)</li>
                            <li>You can re-run the setup wizard or manually create tables in your database</li>
                            <li>Start by uploading some media files to the Media Library</li>
                            <li>Once you have tables, you can link media files to your content</li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Enhanced table management
     */
    private function renderTableManagement($table, $action, $id) {
        $structure = $this->getTableStructure($table);
        $records = $this->getRecords($table, 1, 50);
        $mediaField = $this->hasMediaLinking($table);
        
        if ($action === 'edit' && $id) {
            $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                $mediaFields = $this->getMediaFields($table);
                $this->renderEditForm($table, $structure, $record, $mediaFields);
                return;
            }
        } elseif ($action === 'create') {
            $mediaFields = $this->getMediaFields($table);
            $this->renderCreateForm($table, $structure, $mediaFields);
            return;
        }
        
        ?>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars(ucfirst($table)) ?> Management</h2>
                    <p class="text-gray-600 mt-1">
                        <?php if ($table === 'media'): ?>
                            Upload and manage your media files. Supported formats: images, PDFs, and text files.
                        <?php else: ?>
                            Manage your <?= htmlspecialchars($table) ?> content. <?= $mediaField ? 'This table supports media linking.' : '' ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if ($table !== 'media'): ?>
                    <a href="?table=<?= urlencode($table) ?>&action=create" 
                       class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-lg transform hover:scale-105 transition-all">
                        Add New Record
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($table === 'media'): ?>
                <!-- Media Upload Section -->
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Upload New Media</h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_media">
                        
                        <div>
                            <label for="media_file" class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                            <input type="file" id="media_file" name="media_file" required accept="image/*,.pdf,.txt"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-2">Supported: Images (JPG, PNG, GIF, WebP), PDFs, Text files. Max size: 5MB</p>
                        </div>
                        
                        <div>
                            <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                            <input type="text" id="tags" name="tags" placeholder="e.g., banner, product, thumbnail"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-2">Add comma-separated tags to help organize and find media files</p>
                        </div>
                        
                        <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                            Upload File
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Records Table -->
            <?php if (!empty($records)): ?>
                <?php if ($table === 'media'): ?>
                    <!-- Media Gallery View -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($records as $record): ?>
                            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
                                <div class="aspect-square bg-gray-100 relative overflow-hidden">
                                    <?php 
                                    $isImage = in_array(strtolower(pathinfo($record['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    if ($isImage): ?>
                                        <img src="<?= htmlspecialchars($record['path']) ?>" 
                                             alt="<?= htmlspecialchars($record['original_filename']) ?>"
                                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Overlay actions -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center opacity-0 hover:opacity-100">
                                        <div class="flex space-x-2">
                                            <a href="<?= htmlspecialchars($record['path']) ?>" target="_blank"
                                               class="px-3 py-2 bg-white text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-100">
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                View
                                            </a>
                                            <a href="?table=media&action=edit&id=<?= $record['id'] ?>"
                                               class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </a>
                                            <button onclick="deleteRecord('media', <?= $record['id'] ?>, '<?= htmlspecialchars($record['original_filename']) ?>')"
                                                    class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Media info -->
                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 truncate mb-1" title="<?= htmlspecialchars($record['original_filename']) ?>">
                                        <?= htmlspecialchars($record['original_filename']) ?>
                                    </h3>
                                    <div class="text-sm text-gray-500 space-y-1">
                                        <div class="flex justify-between">
                                            <span>Size:</span>
                                            <span><?= number_format($record['file_size'] / 1024, 1) ?> KB</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Type:</span>
                                            <span><?= htmlspecialchars($record['mime_type']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Uploaded:</span>
                                            <span><?= date('M j, Y', strtotime($record['uploaded_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <?php foreach ($structure as $field): ?>
                                        <th class="px-6 py-4 text-left text-sm font-medium text-gray-700 uppercase tracking-wider">
                                            <?= htmlspecialchars(str_replace('_', ' ', $field['name'])) ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <?php foreach ($structure as $field): ?>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <?php 
                                                $value = $record[$field['name']] ?? '';
                                                
                                                // Check field types using metadata
                                                $mediaFields = $this->getMediaFields($table);
                                                $foreignFields = $this->getForeignKeyFields($table);
                                                $isMediaField = isset($mediaFields[$field['name']]);
                                                $isForeignField = isset($foreignFields[$field['name']]);
                                                
                                                if ($table === 'media' && $field['name'] === 'path'): ?>
                                                    <a href="<?= htmlspecialchars($value) ?>" target="_blank" 
                                                       class="text-blue-600 hover:text-blue-800 font-medium">
                                                        View File
                                                    </a>
                                                <?php elseif ($table === 'media' && $field['name'] === 'file_size'): ?>
                                                    <?= number_format($value / 1024, 1) ?> KB
                                                <?php elseif ($isMediaField && $value): ?>
                                                    <?php $this->renderMediaTableCell($field['name'], $mediaFields[$field['name']], $value); ?>
                                                <?php elseif ($isForeignField && $value): ?>
                                                    <?php $this->renderForeignKeyTableCell($foreignFields[$field['name']]['table'], $foreignFields[$field['name']]['display'], $value); ?>
                                                <?php else: ?>
                                                    <?php 
                                                    $displayValue = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                                                    echo htmlspecialchars($displayValue);
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="?table=<?= urlencode($table) ?>&action=edit&id=<?= $record['id'] ?>" 
                                                   class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 text-xs font-medium">
                                                    Edit
                                                </a>
                                                <button onclick="deleteRecord('<?= $table ?>', <?= $record['id'] ?>, '<?= htmlspecialchars($record['original_filename'] ?? $record['title'] ?? $record['name'] ?? '') ?>')" 
                                                        class="px-3 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200 text-xs font-medium">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-12 text-center border border-gray-200">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No records found</h3>
                    <p class="text-gray-600 mb-6">
                        <?php if ($table === 'media'): ?>
                            Upload your first media file to get started!
                        <?php else: ?>
                            Create your first <?= htmlspecialchars($table) ?> record to get started!
                        <?php endif; ?>
                    </p>
                    <?php if ($table !== 'media'): ?>
                        <a href="?table=<?= urlencode($table) ?>&action=create" 
                           class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                            Create First Record
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Enhanced create form
     */
    private function renderCreateForm($table, $structure, $mediaFields) {
        $allMedia = $this->getAllMedia();
        
        ?>
        <div class="space-y-6">
            <div>
                <h2 class="text-3xl font-bold text-gray-800">Create New <?= htmlspecialchars(ucfirst($table)) ?> Record</h2>
                <p class="text-gray-600 mt-1">Fill in the form below to create a new record.</p>
            </div>
            
            <form method="POST" class="bg-white rounded-lg shadow p-6 border border-gray-200 space-y-6">
                <input type="hidden" name="action" value="create_record">
                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                
                <?php foreach ($structure as $field): ?>
                    <?php if ($field['name'] !== 'id'): ?>
                        <div>
                            <label for="<?= htmlspecialchars($field['name']) ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $field['name']))) ?>
                                <?php if (isset($mediaFields[$field['name']])): ?>
                                    <span class="text-sm text-blue-600">(Media Field - <?= ucfirst($mediaFields[$field['name']]) ?>)</span>
                                <?php endif; ?>
                                <?php $foreignFields = $this->getForeignKeyFields($table); ?>
                                <?php if (isset($foreignFields[$field['name']])): ?>
                                    <span class="text-sm text-green-600">(Links to <?= htmlspecialchars($foreignFields[$field['name']]['table']) ?>)</span>
                                <?php endif; ?>
                            </label>
                            <?php 
                            if (isset($mediaFields[$field['name']])) {
                                $this->renderMediaField($field['name'], $mediaFields[$field['name']], '', $allMedia);
                            } else {
                                $foreignFields = $this->getForeignKeyFields($table);
                                if (isset($foreignFields[$field['name']])) {
                                    $this->renderForeignKeyField($field['name'], $foreignFields[$field['name']]['table'], $foreignFields[$field['name']]['display'], '');
                                } else {
                                    $this->renderFormField($field, '', $table);
                                }
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="flex space-x-4 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                        Create Record
                    </button>
                    <a href="?table=<?= urlencode($table) ?>" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Enhanced edit form
     */
    private function renderEditForm($table, $structure, $record, $mediaFields) {
        $allMedia = $this->getAllMedia();
        $activeLanguages = $this->getActiveLanguages();
        $currentLang = $this->getCurrentLanguage();
        
        ?>
        <div class="space-y-6">
            <div>
                <h2 class="text-3xl font-bold text-gray-800">Edit <?= htmlspecialchars(ucfirst($table)) ?> Record</h2>
                <p class="text-gray-600 mt-1">Update the information below to modify this record.</p>
            </div>

            <!-- Language Tabs (if multiple languages available) -->
            <?php if (count($activeLanguages) > 1): ?>
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <?php foreach ($activeLanguages as $index => $lang): ?>
                            <button type="button" 
                                    onclick="switchTab('<?= $lang['code'] ?>')"
                                    class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $index === 0 ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>"
                                    data-tab="<?= $lang['code'] ?>">
                                <?= htmlspecialchars($lang['native_name']) ?>
                                <?php if ($lang['is_default']): ?>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Default</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                </div>
                
                <!-- Tab Content -->
                <?php foreach ($activeLanguages as $index => $lang): ?>
                    <div id="tab-<?= $lang['code'] ?>" class="tab-content p-6 <?= $index !== 0 ? 'hidden' : '' ?>">
                        <?php if ($lang['is_default']): ?>
                            <!-- Default Language Form -->
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_record">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                                
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                    <?= htmlspecialchars($lang['name']) ?> Content
                                    <?php if ($lang['is_default']): ?>
                                        <span class="text-sm font-normal text-gray-600">(Original Language)</span>
                                    <?php endif; ?>
                                </h3>
                                
                                <?php foreach ($structure as $field): ?>
                                    <?php if ($field['name'] !== 'id'): ?>
                                        <div>
                                            <label for="<?= htmlspecialchars($field['name']) ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $field['name']))) ?>
                                                <?php if (isset($mediaFields[$field['name']])): ?>
                                                    <span class="text-sm text-blue-600">(Media Field - <?= ucfirst($mediaFields[$field['name']]) ?>)</span>
                                                <?php endif; ?>
                                                <?php $foreignFields = $this->getForeignKeyFields($table); ?>
                                                <?php if (isset($foreignFields[$field['name']])): ?>
                                                    <span class="text-sm text-green-600">(Links to <?= htmlspecialchars($foreignFields[$field['name']]['table']) ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                            <?php 
                                            if (isset($mediaFields[$field['name']])) {
                                                $this->renderMediaField($field['name'], $mediaFields[$field['name']], $record[$field['name']] ?? '', $allMedia);
                                            } else {
                                                $foreignFields = $this->getForeignKeyFields($table);
                                                if (isset($foreignFields[$field['name']])) {
                                                    $this->renderForeignKeyField($field['name'], $foreignFields[$field['name']]['table'], $foreignFields[$field['name']]['display'], $record[$field['name']] ?? '');
                                                } else {
                                                    $this->renderFormField($field, $record[$field['name']] ?? '', $table);
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <div class="flex space-x-4 pt-6 border-t border-gray-200">
                                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                                        Update <?= htmlspecialchars($lang['name']) ?> Content
                                    </button>
                                    <a href="?table=<?= urlencode($table) ?>" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Translation Form -->
                            <form method="POST" class="space-y-6" onsubmit="return prepareTranslationForm(this, '<?= $lang['code'] ?>')">
                                <input type="hidden" name="action" value="save_translation">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="record_id" value="<?= htmlspecialchars($record['id']) ?>">
                                <input type="hidden" name="language_code" value="<?= htmlspecialchars($lang['code']) ?>">
                                
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                    <?= htmlspecialchars($lang['name']) ?> Translation
                                </h3>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                    <p class="text-blue-800 text-sm">
                                        <strong>💡 Tip:</strong> Translate the content below into <?= htmlspecialchars($lang['name']) ?>. 
                                        You can see the original content by switching to the "<?= htmlspecialchars($activeLanguages[0]['name']) ?>" tab.
                                    </p>
                                </div>
                                
                                <?php foreach ($structure as $field): ?>
                                    <?php 
                                    $originalFieldType = $this->getOriginalFieldType($table, $field['name']);
                                    $fieldTypeToCheck = $originalFieldType ?: strtolower($field['type']);
                                    $isDateField = in_array($fieldTypeToCheck, ['date', 'datetime']);
                                    ?>
                                    <?php if ($field['name'] !== 'id' && !isset($mediaFields[$field['name']]) && !isset($this->getForeignKeyFields($table)[$field['name']]) && !$isDateField): ?>
                                        <?php 
                                        $translatedValue = $this->getTranslation($table, $record['id'], $field['name'], $lang['code']);
                                        $originalValue = $record[$field['name']] ?? '';
                                        ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $field['name']))) ?>
                                            </label>
                                            
                                            <!-- Original Value Reference -->
                                            <div class="mb-2 p-3 bg-gray-50 border border-gray-200 rounded">
                                                <div class="text-xs text-gray-500 mb-1">Original (<?= htmlspecialchars($activeLanguages[0]['name']) ?>):</div>
                                                <div class="text-sm text-gray-700 italic">
                                                    <?= htmlspecialchars(strlen($originalValue) > 100 ? substr($originalValue, 0, 100) . '...' : $originalValue) ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Translation Input -->
                                            <?php 
                                            $fieldInfo = ['name' => $field['name'], 'type' => $field['type']];
                                            ?>
                                            <div class="translation-field" data-field-name="<?= htmlspecialchars($field['name']) ?>" data-language="<?= htmlspecialchars($lang['code']) ?>">
                                                <?php $this->renderTranslationFormField($fieldInfo, $translatedValue, $lang['name'], $table); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <div class="flex space-x-4 pt-6 border-t border-gray-200">
                                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                                        Save <?= htmlspecialchars($lang['name']) ?> Translations
                                    </button>
                                    <button type="button" onclick="clearTranslations('<?= $lang['code'] ?>')" 
                                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                                        Clear All
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <!-- Single Language Form (fallback) -->
                <form method="POST" class="bg-white rounded-lg shadow p-6 border border-gray-200 space-y-6">
                    <input type="hidden" name="action" value="update_record">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                    
                    <?php foreach ($structure as $field): ?>
                        <?php if ($field['name'] !== 'id'): ?>
                            <div>
                                <label for="<?= htmlspecialchars($field['name']) ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $field['name']))) ?>
                                    <?php if (isset($mediaFields[$field['name']])): ?>
                                        <span class="text-sm text-blue-600">(Media Field - <?= ucfirst($mediaFields[$field['name']]) ?>)</span>
                                    <?php endif; ?>
                                    <?php $foreignFields = $this->getForeignKeyFields($table); ?>
                                    <?php if (isset($foreignFields[$field['name']])): ?>
                                        <span class="text-sm text-green-600">(Links to <?= htmlspecialchars($foreignFields[$field['name']]['table']) ?>)</span>
                                    <?php endif; ?>
                                </label>
                                <?php 
                                if (isset($mediaFields[$field['name']])) {
                                    $this->renderMediaField($field['name'], $mediaFields[$field['name']], $record[$field['name']] ?? '', $allMedia);
                                } else {
                                    $foreignFields = $this->getForeignKeyFields($table);
                                    if (isset($foreignFields[$field['name']])) {
                                        $this->renderForeignKeyField($field['name'], $foreignFields[$field['name']]['table'], $foreignFields[$field['name']]['display'], $record[$field['name']] ?? '');
                                    } else {
                                        $this->renderFormField($field, $record[$field['name']] ?? '', $table);
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="flex space-x-4 pt-6 border-t border-gray-200">
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                            Update Record
                        </button>
                        <a href="?table=<?= urlencode($table) ?>" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render API Documentation Page
     */
    private function renderApiDocumentation($userTables) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        ?>
        <div class="space-y-8">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl p-8 text-white">
                <h1 class="text-4xl font-bold mb-4">REST API Documentation</h1>
                <p class="text-xl opacity-90">Access your CMS data from any website, app, or service using our REST API.</p>
                <div class="mt-6 bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <p class="text-sm opacity-75 mb-1">Base URL:</p>
                    <code class="text-lg font-mono bg-black/20 px-3 py-1 rounded"><?= htmlspecialchars($baseUrl) ?></code>
                </div>
            </div>

            <!-- Quick Start -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">🚀 Quick Start</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">JavaScript Example</h3>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm"><code>// Get latest articles with French translations
fetch('<?= htmlspecialchars($baseUrl) ?>?api=records&table=articles&limit=5&lang=fr')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      data.data.forEach(article => {
        console.log('Original:', article.title);
        console.log('French:', article.translations.title);
        // Media files expanded as article.thumbnail_media
        // Related data expanded as article.category_id_data
      });
    }
  });

// Get available languages
fetch('<?= htmlspecialchars($baseUrl) ?>?api=languages')
  .then(response => response.json())
  .then(data => console.log(data.data.languages));</code></pre>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">PHP Example</h3>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm"><code>// Get articles with Spanish translations
$url = '<?= htmlspecialchars($baseUrl) ?>?api=records&table=articles&lang=es';
$response = file_get_contents($url);
$data = json_decode($response, true);

foreach ($data['data'] as $article) {
    $title = $article['translations']['title'] ?? $article['title'];
    $content = $article['translations']['content'] ?? $article['content'];
    echo "&lt;h2&gt;{$title}&lt;/h2&gt;";
    echo "&lt;p&gt;{$content}&lt;/p&gt;";
}

// Get all translations for a specific article
$url = '<?= htmlspecialchars($baseUrl) ?>?api=translations&table=articles&record_id=1';
$translations = json_decode(file_get_contents($url), true);</code></pre>
                    </div>
                </div>
            </div>

            <!-- Available Endpoints -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">📋 Available Endpoints</h2>
                
                <!-- Tables Endpoint -->
                <div class="mb-8 border-l-4 border-blue-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Get All Tables</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-blue-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=tables</code>
                    </div>
                    <p class="text-gray-600 mb-3">Returns information about all available tables and their structure.</p>
                    <details class="mb-4">
                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800 font-medium">Show Example Response</summary>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm mt-2"><code>{
  "success": true,
  "data": [
    {
      "name": "articles",
      "record_count": 25,
      "fields": [
        {"name": "id", "type": "INTEGER"},
        {"name": "title", "type": "TEXT"},
        {"name": "content", "type": "TEXT"}
      ]
    }
  ],
  "meta": {
    "total_tables": 1,
    "timestamp": "2024-01-15T10:30:00+00:00"
  }
}</code></pre>
                    </details>
                </div>

                <!-- Records Endpoint -->
                <div class="mb-8 border-l-4 border-green-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Get Records from Table</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-green-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=records&table=TABLE_NAME</code>
                    </div>
                    <p class="text-gray-600 mb-3">Retrieve records from a specific table with pagination and sorting options.</p>
                    
                    <h4 class="font-semibold text-gray-700 mb-2">Parameters:</h4>
                    <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                        <li><code class="bg-gray-100 px-2 py-1 rounded">table</code> - Table name (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">limit</code> - Number of records (default: 10, max: 100)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">offset</code> - Skip records (default: 0)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">order_by</code> - Sort field (default: id)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">order_dir</code> - Sort direction: ASC or DESC (default: DESC)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">lang</code> - Language code for translations (optional, e.g., 'fr', 'es')</li>
                    </ul>
                    
                    <?php if (!empty($userTables)): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-800 mb-2">Try with your tables:</h4>
                        <div class="space-y-2">
                            <?php foreach ($userTables as $table): ?>
                                <div>
                                    <code class="text-blue-600 font-mono text-sm">
                                        <a href="<?= htmlspecialchars($baseUrl) ?>?api=records&table=<?= urlencode($table) ?>&limit=5" 
                                           target="_blank" class="hover:underline">
                                            <?= htmlspecialchars($baseUrl) ?>?api=records&table=<?= htmlspecialchars($table) ?>&limit=5
                                        </a>
                                    </code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Single Record Endpoint -->
                <div class="mb-8 border-l-4 border-purple-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Get Single Record</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-purple-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=record&table=TABLE_NAME&id=RECORD_ID</code>
                    </div>
                    <p class="text-gray-600 mb-3">Get a specific record by ID with all related media and foreign key data expanded.</p>
                    
                    <h4 class="font-semibold text-gray-700 mb-2">Parameters:</h4>
                    <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                        <li><code class="bg-gray-100 px-2 py-1 rounded">table</code> - Table name (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">id</code> - Record ID (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">lang</code> - Language code for translations (optional, e.g., 'fr', 'es')</li>
                    </ul>
                </div>

                <!-- Media Endpoint -->
                <div class="mb-8 border-l-4 border-orange-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Get Media Files</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-orange-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=media</code>
                    </div>
                    <p class="text-gray-600 mb-3">Retrieve media files with optional tag filtering.</p>
                    
                    <h4 class="font-semibold text-gray-700 mb-2">Parameters:</h4>
                    <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                        <li><code class="bg-gray-100 px-2 py-1 rounded">limit</code> - Number of files (default: 10, max: 100)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">offset</code> - Skip files (default: 0)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">tag</code> - Filter by tag (optional)</li>
                    </ul>
                    
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <code class="text-orange-600 font-mono text-sm">
                            <a href="<?= htmlspecialchars($baseUrl) ?>?api=media&limit=10" 
                               target="_blank" class="hover:underline">
                                <?= htmlspecialchars($baseUrl) ?>?api=media&limit=10
                            </a>
                        </code>
                    </div>
                </div>

                <!-- Search Endpoint -->
                <div class="mb-8 border-l-4 border-red-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Search Records</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-red-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=search&table=TABLE_NAME&q=SEARCH_QUERY</code>
                    </div>
                    <p class="text-gray-600 mb-3">Search through records in a specific table.</p>
                    
                    <h4 class="font-semibold text-gray-700 mb-2">Parameters:</h4>
                    <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                        <li><code class="bg-gray-100 px-2 py-1 rounded">table</code> - Table name (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">q</code> - Search query (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">field</code> - Specific field to search (optional)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">limit</code> - Number of results (default: 10, max: 100)</li>
                    </ul>
                </div>

                <!-- Languages Endpoint -->
                <div class="mb-8 border-l-4 border-indigo-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">🌍 Get Available Languages</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-indigo-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=languages</code>
                    </div>
                    <p class="text-gray-600 mb-3">Retrieve all available languages and current language settings.</p>
                    
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                        <code class="text-indigo-600 font-mono text-sm">
                            <a href="<?= htmlspecialchars($baseUrl) ?>?api=languages" 
                               target="_blank" class="hover:underline">
                                <?= htmlspecialchars($baseUrl) ?>?api=languages
                            </a>
                        </code>
                    </div>

                    <details class="mb-4">
                        <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 font-medium">Show Example Response</summary>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm mt-2"><code>{
  "success": true,
  "data": {
    "languages": [
      {
        "id": 1,
        "code": "en",
        "name": "English",
        "native_name": "English",
        "is_default": true,
        "is_active": true
      },
      {
        "id": 2,
        "code": "fr",
        "name": "French",
        "native_name": "Français",
        "is_default": false,
        "is_active": true
      }
    ],
    "default_language": "en",
    "current_language": "en"
  },
  "meta": {
    "total_languages": 2,
    "timestamp": "2024-01-15T10:30:00+00:00"
  }
}</code></pre>
                    </details>
                </div>

                <!-- Translations Endpoint -->
                <div class="mb-8 border-l-4 border-pink-500 pl-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">🌐 Get Translations</h3>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <code class="text-pink-600 font-mono">GET <?= htmlspecialchars($baseUrl) ?>?api=translations&table=TABLE_NAME&record_id=ID</code>
                    </div>
                    <p class="text-gray-600 mb-3">Retrieve translations for a specific record across all languages or for a specific language.</p>
                    
                    <h4 class="font-semibold text-gray-700 mb-2">Parameters:</h4>
                    <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                        <li><code class="bg-gray-100 px-2 py-1 rounded">table</code> - Table name (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">record_id</code> - Record ID (required)</li>
                        <li><code class="bg-gray-100 px-2 py-1 rounded">lang</code> - Language code (optional, e.g., 'fr', 'es')</li>
                    </ul>

                    <?php if (!empty($userTables)): ?>
                    <div class="bg-pink-50 border border-pink-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-pink-800 mb-2">Example URLs:</h4>
                        <div class="space-y-2 text-sm">
                            <?php foreach ($userTables as $table): ?>
                                <div>
                                    <p class="text-pink-700 font-medium mb-1">Get all translations for <?= htmlspecialchars($table) ?> record #1:</p>
                                    <code class="text-pink-600 font-mono">
                                        <a href="<?= htmlspecialchars($baseUrl) ?>?api=translations&table=<?= urlencode($table) ?>&record_id=1" 
                                           target="_blank" class="hover:underline">
                                            <?= htmlspecialchars($baseUrl) ?>?api=translations&table=<?= htmlspecialchars($table) ?>&record_id=1
                                        </a>
                                    </code>
                                </div>
                                <div class="mb-3">
                                    <p class="text-pink-700 font-medium mb-1">Get French translation only:</p>
                                    <code class="text-pink-600 font-mono">
                                        <a href="<?= htmlspecialchars($baseUrl) ?>?api=translations&table=<?= urlencode($table) ?>&record_id=1&lang=fr" 
                                           target="_blank" class="hover:underline">
                                            <?= htmlspecialchars($baseUrl) ?>?api=translations&table=<?= htmlspecialchars($table) ?>&record_id=1&lang=fr
                                        </a>
                                    </code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <details class="mb-4">
                        <summary class="cursor-pointer text-pink-600 hover:text-pink-800 font-medium">Show Example Response (All Languages)</summary>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm mt-2"><code>{
  "success": true,
  "data": {
    "translations": {
      "fr": {
        "title": {
          "value": "Article d'exemple",
          "updated_at": "2024-01-15 10:30:00"
        },
        "content": {
          "value": "Contenu français...",
          "updated_at": "2024-01-15 10:30:00"
        }
      },
      "es": {
        "title": {
          "value": "Artículo de muestra",
          "updated_at": "2024-01-15 11:00:00"
        }
      }
    },
    "table": "articles",
    "record_id": "1"
  },
  "meta": {
    "total_languages": 2,
    "timestamp": "2024-01-15T12:00:00+00:00"
  }
}</code></pre>
                    </details>
                </div>

                <!-- Multi-language Support -->
                <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">🎌 Multi-language Support</h3>
                    <p class="text-gray-600 mb-4">All content endpoints support multi-language translations by adding the <code class="bg-white px-2 py-1 rounded border">lang</code> parameter:</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Records with Translations:</h4>
                            <code class="text-blue-600 font-mono text-sm bg-white p-2 rounded border block">
                                <?= htmlspecialchars($baseUrl) ?>?api=records&table=articles&lang=fr
                            </code>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Single Record with Translation:</h4>
                            <code class="text-blue-600 font-mono text-sm bg-white p-2 rounded border block">
                                <?= htmlspecialchars($baseUrl) ?>?api=record&table=articles&id=1&lang=es
                            </code>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg p-4 border border-blue-200">
                        <h4 class="font-semibold text-gray-700 mb-2">📝 Translation Response Format:</h4>
                        <p class="text-gray-600 text-sm mb-2">When using the <code>lang</code> parameter, records include a <code>translations</code> object and <code>language</code> field:</p>
                        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-x-auto"><code>{
  "id": 1,
  "title": "Original Title",
  "content": "Original content...",
  "translations": {
    "title": "Titre français",
    "content": "Contenu français..."
  },
  "language": "fr"
}</code></pre>
                    </div>
                </div>
            </div>

            <!-- Response Format -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">📄 Response Format</h2>
                <p class="text-gray-600 mb-4">All API responses follow a consistent JSON format:</p>
                
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm"><code>{
  "success": true,
  "data": [...], // Your actual data
  "meta": {
    "total": 25,
    "limit": 10,
    "offset": 0,
    "has_more": true,
    "timestamp": "2024-01-15T10:30:00+00:00"
  }
}</code></pre>

                <h3 class="text-lg font-semibold text-gray-800 mt-6 mb-3">🔗 Data Expansion</h3>
                <p class="text-gray-600 mb-4">The API automatically expands related data:</p>
                
                <ul class="list-disc list-inside text-gray-600 space-y-2">
                    <li><strong>Media Fields:</strong> Original field + <code class="bg-gray-100 px-2 py-1 rounded">_media</code> suffix with full file information</li>
                    <li><strong>Foreign Keys:</strong> Original field + <code class="bg-gray-100 px-2 py-1 rounded">_data</code> suffix with related record data</li>
                    <li><strong>Translations:</strong> When <code class="bg-gray-100 px-2 py-1 rounded">lang</code> parameter is used, adds <code class="bg-gray-100 px-2 py-1 rounded">translations</code> object and <code class="bg-gray-100 px-2 py-1 rounded">language</code> field</li>
                    <li><strong>CORS Enabled:</strong> API can be called from any domain</li>
                    <li><strong>No Authentication:</strong> Read-only access for public content consumption</li>
                </ul>
            </div>

        </div>
        <?php
    }
    
    /**
     * Handle creating new table
     */
    private function handleCreateTable($data) {
        $tableName = trim($data['table_name'] ?? '');
        $fieldsData = $data['fields'] ?? [];
        
        if (empty($tableName) || empty($fieldsData) || !is_array($fieldsData)) {
            return ['error' => 'Table name and fields are required'];
        }
        
        // Sanitize table name
        $tableName = $this->sanitizeFieldName($tableName);
        
        // Check if table already exists
        if (in_array($tableName, array_merge($this->getUserTables(), ['media', 'users', 'table_field_meta']))) {
            return ['error' => 'Table name already exists'];
        }
        
        // Convert form data to expected format
        $fields = [];
        $fieldNames = $fieldsData['name'] ?? [];
        $fieldTypes = $fieldsData['type'] ?? [];
        
        // Validate we have field data
        if (empty($fieldNames) || empty($fieldTypes) || !is_array($fieldNames) || !is_array($fieldTypes)) {
            return ['error' => 'At least one field is required'];
        }
        
        for ($i = 0; $i < count($fieldNames); $i++) {
            if (!empty($fieldNames[$i]) && !empty($fieldTypes[$i])) {
                $fields[] = [
                    'name' => trim($fieldNames[$i]),
                    'type' => $fieldTypes[$i]
                ];
            }
        }
        
        // Ensure we have at least one valid field
        if (empty($fields)) {
            return ['error' => 'At least one valid field is required'];
        }
        
        try {
            // Create the table
            $tableConfig = [$tableName => ['fields' => $fields]];
            $this->createUserTables($tableConfig);
            
            return ['success' => "Table '{$tableName}' created successfully!"];
        } catch (Exception $e) {
            return ['error' => 'Failed to create table: ' . $e->getMessage()];
        }
    }

    private function addTableField($table, $data) {
        $fieldName = trim($data['field_name'] ?? '');
        $fieldType = $data['field_type'] ?? '';
        
        if (empty($fieldName) || empty($fieldType)) {
            return ['error' => 'Field name and type are required'];
        }
        
        // Sanitize field name
        $fieldName = $this->sanitizeFieldName($fieldName);
        
        // Check if field already exists
        $structure = $this->getTableStructure($table);
        foreach ($structure as $field) {
            if ($field['name'] === $fieldName) {
                return ['error' => 'Field with this name already exists'];
            }
        }
        
        try {
            // Map field type to SQL type
            $sqlType = $this->mapFieldType($fieldType);
            
            // Add column to table
            $this->db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$fieldName}` {$sqlType}");
            
            // Store field metadata
            $isMediaField = in_array($fieldType, ['media_single', 'media_multiple']);
            $mediaType = $isMediaField ? ($fieldType === 'media_single' ? 'single' : 'multiple') : null;
            $isForeignKey = $fieldType === 'foreign_key';
            
            $stmt = $this->db->prepare("INSERT INTO table_field_meta (table_name, field_name, field_type, is_media_field, media_type, is_foreign_key) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$table, $fieldName, $fieldType, $isMediaField ? 1 : 0, $mediaType, $isForeignKey ? 1 : 0]);
            
            return ['success' => "Field '{$fieldName}' added successfully!"];
        } catch (PDOException $e) {
            return ['error' => 'Failed to add field: ' . $e->getMessage()];
        }
    }
    
    private function switchLanguage($languageCode) {
        $stmt = $this->db->prepare("SELECT * FROM languages WHERE code = ? AND is_active = 1");
        $stmt->execute([$languageCode]);
        $language = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$language) {
            return ['error' => 'Invalid language code'];
        }
        
        $this->setCurrentLanguage($languageCode);
        return ['success' => "Language switched to {$language['name']}"];
    }
    
    private function saveTranslation($data) {
        $table = $data['table'] ?? '';
        $recordId = $data['record_id'] ?? '';
        $fieldName = $data['field_name'] ?? '';
        $languageCode = $data['language_code'] ?? '';
        $translatedValue = $data['translated_value'] ?? '';
        
        // Decode URL-encoded content
        $translatedValue = urldecode($translatedValue);
        
        if (empty($table) || empty($recordId) || empty($fieldName) || empty($languageCode)) {
            return ['error' => 'Missing required translation data'];
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO content_translations 
                (table_name, record_id, field_name, language_code, translated_value, updated_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$table, $recordId, $fieldName, $languageCode, $translatedValue]);
            
            
            return ['success' => 'Translation saved successfully'];
        } catch (PDOException $e) {
            return ['error' => 'Failed to save translation: ' . $e->getMessage()];
        }
    }
    
    private function getTranslation($table, $recordId, $fieldName, $languageCode) {
        $stmt = $this->db->prepare("
            SELECT translated_value FROM content_translations 
            WHERE table_name = ? AND record_id = ? AND field_name = ? AND language_code = ?
        ");
        $stmt->execute([$table, $recordId, $fieldName, $languageCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $value = $result ? $result['translated_value'] : null;
        
        return $value;
    }
    
    private function saveTranslationBatch($data) {
        $table = $data['table'] ?? '';
        $recordId = $data['record_id'] ?? '';
        $languageCode = $data['language_code'] ?? '';
        $translations = $data['translations'] ?? [];
        
        if (empty($table) || empty($recordId) || empty($languageCode)) {
            return ['error' => 'Missing required translation data'];
        }
        
        if (empty($translations) || !is_array($translations)) {
            return ['error' => 'No translations provided'];
        }
        
        $savedCount = 0;
        $errors = [];
        
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO content_translations 
                (table_name, record_id, field_name, language_code, translated_value, updated_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            foreach ($translations as $fieldName => $translatedValue) {
                if (!empty($fieldName) && !empty(trim($translatedValue))) {
                    $stmt->execute([$table, $recordId, $fieldName, $languageCode, $translatedValue]);
                    $savedCount++;
                }
            }
            
            $this->db->commit();
            
            if ($savedCount > 0) {
                return ['success' => "Saved $savedCount translations successfully"];
            } else {
                return ['error' => 'No translations were saved (all fields were empty)'];
            }
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to save translations: ' . $e->getMessage()];
        }
    }
    
    private function renderTranslationFormField($field, $value, $languageName, $tableName = '') {
        $fieldName = "translations[" . htmlspecialchars($field['name']) . "]";
        $placeholder = "Enter " . htmlspecialchars($languageName) . " translation...";
        
        // Get original field type from metadata to determine proper input type
        $originalFieldType = $this->getOriginalFieldType($tableName, $field['name']);
        $fieldTypeToCheck = $originalFieldType ?: strtolower($field['type']);
        
        // Use the same logic as the regular form fields
        if ($fieldTypeToCheck === 'textarea') {
            // Long text field - use WYSIWYG editor (same as original)
            $editorId = 'translation_' . $tableName . '_' . $field['name'] . '_' . preg_replace('/[^a-z0-9]/i', '', $languageName) . '_editor';
            $hiddenId = 'translation_' . $tableName . '_' . $field['name'] . '_' . preg_replace('/[^a-z0-9]/i', '', $languageName) . '_hidden';
            ?>
            <div class="wysiwyg-container">
                <!-- Toolbar (same as original) -->
                <div class="wysiwyg-toolbar border border-gray-300 rounded-t-lg bg-gray-50 p-2 flex items-center space-x-2 flex-wrap">
                    <button type="button" onclick="formatText(this, 'bold')" class="wysiwyg-btn" title="Bold">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15.6,10.79C16.57,10.11 17.25,9.02 17.25,8C17.25,5.74 15.5,4 13.25,4H7V18H14.04C16.14,18 17.75,16.3 17.75,14.21C17.75,12.69 16.89,11.39 15.6,10.79M10,6.5H13C13.83,6.5 14.5,7.17 14.5,8C14.5,8.83 13.83,9.5 13,9.5H10V6.5M14,15.5H10V12.5H14C14.83,12.5 15.5,13.17 15.5,14C15.5,14.83 14.83,15.5 14,15.5Z"/>
                        </svg>
                    </button>
                    <button type="button" onclick="formatText(this, 'italic')" class="wysiwyg-btn" title="Italic">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M10,4V7H12.21L8.79,15H6V18H14V15H11.79L15.21,7H18V4H10Z"/>
                        </svg>
                    </button>
                    <button type="button" onclick="formatText(this, 'underline')" class="wysiwyg-btn" title="Underline">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5,21H19V19H5V21M12,17A6,6 0 0,0 18,11V3H15.5V11A3.5,3.5 0 0,1 12,14.5A3.5,3.5 0 0,1 8.5,11V3H6V11A6,6 0 0,0 12,17Z"/>
                        </svg>
                    </button>
                    <div class="border-l border-gray-300 h-6 mx-2"></div>
                    <button type="button" onclick="formatText(this, 'insertUnorderedList')" class="wysiwyg-btn" title="Bullet List">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z"/>
                        </svg>
                    </button>
                    <button type="button" onclick="formatText(this, 'insertOrderedList')" class="wysiwyg-btn" title="Numbered List">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M7,13V11H21V13H7M7,19V17H21V19H7M7,7V5H21V7H7M3,8V5H2V4H4V8H3M2,17V16H5V20H2V19H4V18.5H3V17.5H4V17H2M4.25,10A0.75,0.75 0 0,1 5,10.75C5,10.95 4.92,11.14 4.79,11.27L3.12,13H5V14H2V13.08L4,11H2V10H4.25Z"/>
                        </svg>
                    </button>
                    <div class="border-l border-gray-300 h-6 mx-2"></div>
                    <button type="button" onclick="formatText(this, 'createLink')" class="wysiwyg-btn" title="Insert Link">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z"/>
                        </svg>
                    </button>
                    <div class="border-l border-gray-300 h-6 mx-2"></div>
                    <button type="button" onclick="formatText(this, 'removeFormat')" class="wysiwyg-btn" title="Clear Formatting">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6,5V5.18L8.82,8H11.22L10.5,9.68L12.6,11.78C13.25,10.8 13.24,9.54 12.65,8.5C12.26,7.78 11.62,7.26 10.86,7.05L14.3,5.18C14.5,5 14.9,5 15.1,5.18C15.3,5.4 15.3,5.8 15.1,6L12.89,7.89C13.0,7.9 13.1,7.9 13.22,7.9C14.22,8 15.22,8.4 16.22,9.4C17.33,10.5 17.44,12.18 16.55,13.4L19.77,16.62L18.36,18.03L2,1.67L3.41,0.25L6,2.84V5M7.91,10.09L6,8.18V10.09H7.91M6,14H10.36L7.91,11.55H6V14Z"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Editor -->
                <div id="<?= $editorId ?>" 
                     contenteditable="true" 
                     class="wysiwyg-editor border-l border-r border-b border-gray-300 rounded-b-lg p-4 min-h-32 max-h-64 overflow-y-auto focus:outline-none focus:ring-2 focus:ring-blue-500"
                     style="white-space: pre-wrap;"
                     placeholder="<?= $placeholder ?>"
                     oninput="updateHiddenField(this)"><?= $value ? htmlspecialchars_decode($value) : '' ?></div>
                
                <!-- Hidden field for form submission -->
                <input type="hidden" id="<?= $hiddenId ?>" name="<?= $fieldName ?>" value="<?= htmlspecialchars($value) ?>" class="wysiwyg-hidden">
            </div>
            <?php
        } else {
            // All other fields - use regular text input
            ?>
            <input type="text" 
                   name="<?= $fieldName ?>"
                   value="<?= htmlspecialchars($value) ?>"
                   placeholder="<?= $placeholder ?>"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <?php
        }
    }
    
    private function toggleLanguage($data) {
        $languageCode = $data['language_code'] ?? '';
        $activate = $data['activate'] === 'true';
        
        if (empty($languageCode)) {
            return ['error' => 'Language code is required'];
        }
        
        // Don't allow deactivating default language
        $stmt = $this->db->prepare("SELECT is_default FROM languages WHERE code = ?");
        $stmt->execute([$languageCode]);
        $lang = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lang && $lang['is_default'] && !$activate) {
            return ['error' => 'Cannot deactivate the default language'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE languages SET is_active = ? WHERE code = ?");
            $stmt->execute([$activate ? 1 : 0, $languageCode]);
            
            return ['success' => "Language " . ($activate ? 'activated' : 'deactivated') . " successfully"];
        } catch (PDOException $e) {
            return ['error' => 'Failed to update language status: ' . $e->getMessage()];
        }
    }
    
    private function setDefaultLanguage($languageCode) {
        if (empty($languageCode)) {
            return ['error' => 'Language code is required'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Remove default from all languages
            $this->db->exec("UPDATE languages SET is_default = 0");
            
            // Set new default and ensure it's active
            $stmt = $this->db->prepare("UPDATE languages SET is_default = 1, is_active = 1 WHERE code = ?");
            $stmt->execute([$languageCode]);
            
            $this->db->commit();
            
            return ['success' => 'Default language updated successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to set default language: ' . $e->getMessage()];
        }
    }

    
    /**
     * Render Edit Table Structure Page
     */
    private function renderEditTableStructure($table) {
        $structure = $this->getTableStructure($table);
        $metadata = $this->getTableFieldMetadata($table);
        
        ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit Table Structure: <?= htmlspecialchars(ucfirst($table)) ?></h1>
                <p class="text-gray-600">Modify the fields and structure of your <?= htmlspecialchars($table) ?> table.</p>
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 text-sm">
                        <strong>ℹ️ Info:</strong> You can add new fields to extend your table structure. 
                        Existing fields are protected to maintain data integrity and relationships.
                    </p>
                </div>
            </div>

            <!-- Current Fields Display -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Table Structure</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Field Name</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Type</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Special Properties</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($structure as $field): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($field['name']) ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($field['type']) ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        <?php
                                        $fieldMeta = $metadata[$field['name']] ?? null;
                                        if ($fieldMeta) {
                                            if ($fieldMeta['is_media_field']) {
                                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Media Field (' . $fieldMeta['media_type'] . ')</span>';
                                            }
                                            if ($fieldMeta['is_foreign_key']) {
                                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Foreign Key → ' . $fieldMeta['foreign_table'] . '</span>';
                                            }
                                        }
                                        if ($field['name'] === 'id') {
                                            echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Primary Key</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="text-xs text-gray-400">Protected</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add New Field Form -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Add New Field</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_table_field">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="field_name" class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                            <input type="text" id="field_name" name="field_name" required
                                   placeholder="e.g., description, price"
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="field_type" class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                            <select id="field_type" name="field_type" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="text">Text</option>
                                <option value="textarea">Textarea (Long Text)</option>
                                <option value="email">Email</option>
                                <option value="url">URL</option>
                                <option value="number">Number</option>
                                <option value="boolean">Boolean (Yes/No)</option>
                                <option value="date">Date</option>
                                <option value="datetime">Date & Time</option>
                                <option value="media_single">Media (Single File)</option>
                                <option value="media_multiple">Media (Multiple Files)</option>
                                <option value="foreign_key">Foreign Key</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors font-medium">
                                Add Field
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Back Button -->
            <div class="flex space-x-4">
                <a href="?table=<?= urlencode($table) ?>" 
                   class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium">
                    ← Back to <?= htmlspecialchars(ucfirst($table)) ?>
                </a>
            </div>
        </div>

        <?php
    }

    /**
     * Render Language Management Page
     */
    private function renderLanguageManagement() {
        $activeLanguages = $this->getActiveLanguages();
        $allLanguages = $this->db->query("SELECT * FROM languages ORDER BY is_default DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Language Management</h1>
                <p class="text-gray-600">Manage the languages available in your CMS and configure translation settings.</p>
            </div>

            <!-- Current Languages -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Configured Languages</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Language</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Code</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Native Name</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Status</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($allLanguages as $lang): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($lang['name']) ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        <code class="bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($lang['code']) ?></code>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($lang['native_name']) ?></td>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="flex space-x-2">
                                            <?php if ($lang['is_default']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Default</span>
                                            <?php endif; ?>
                                            <?php if ($lang['is_active']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="flex space-x-2">
                                            <?php if (!$lang['is_default']): ?>
                                                <button onclick="toggleLanguageStatus('<?= htmlspecialchars($lang['code']) ?>', <?= $lang['is_active'] ? 'false' : 'true' ?>)" 
                                                        class="px-3 py-1 <?= $lang['is_active'] ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200' ?> rounded text-xs font-medium">
                                                    <?= $lang['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                                <button onclick="setDefaultLanguage('<?= htmlspecialchars($lang['code']) ?>')" 
                                                        class="px-3 py-1 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 text-xs font-medium">
                                                    Set Default
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">Cannot modify default</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Translation Stats -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Translation Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php
                    $userTables = $this->getUserTables();
                    $totalRecords = 0;
                    $totalTranslations = 0;
                    
                    foreach ($userTables as $table) {
                        $count = $this->getTableRecordCount($table);
                        $totalRecords += $count;
                    }
                    
                    $stmt = $this->db->query("SELECT COUNT(*) as count FROM content_translations");
                    $totalTranslations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800"><?= count($activeLanguages) ?></h3>
                                <p class="text-gray-600">Active Languages</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-lg">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.168 18.477 18.582 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800"><?= $totalTranslations ?></h3>
                                <p class="text-gray-600">Total Translations</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-lg">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800"><?= $totalRecords ?></h3>
                                <p class="text-gray-600">Content Records</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Translation Coverage -->
            <?php if (count($activeLanguages) > 1): ?>
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Translation Coverage by Table</h2>
                <div class="space-y-4">
                    <?php foreach ($userTables as $table): 
                        $recordCount = $this->getTableRecordCount($table);
                        if ($recordCount > 0):
                    ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-gray-900"><?= htmlspecialchars(ucfirst($table)) ?></h3>
                                <span class="text-sm text-gray-500"><?= $recordCount ?> records</span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($activeLanguages as $lang): 
                                    if ($lang['is_default']) continue;
                                    
                                    $stmt = $this->db->prepare("
                                        SELECT COUNT(DISTINCT record_id) as translated_count 
                                        FROM content_translations 
                                        WHERE table_name = ? AND language_code = ?
                                    ");
                                    $stmt->execute([$table, $lang['code']]);
                                    $translatedCount = $stmt->fetch(PDO::FETCH_ASSOC)['translated_count'];
                                    $percentage = $recordCount > 0 ? round(($translatedCount / $recordCount) * 100) : 0;
                                ?>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($lang['native_name']) ?></div>
                                        <div class="mt-1">
                                            <div class="bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1"><?= $percentage ?>% (<?= $translatedCount ?>/<?= $recordCount ?>)</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        function toggleLanguageStatus(languageCode, activate) {
            if (confirm(`Are you sure you want to ${activate ? 'activate' : 'deactivate'} this language?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_language">
                    <input type="hidden" name="language_code" value="${languageCode}">
                    <input type="hidden" name="activate" value="${activate}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function setDefaultLanguage(languageCode) {
            if (confirm('Are you sure you want to set this as the default language?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="set_default_language">
                    <input type="hidden" name="language_code" value="${languageCode}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }

    /**
     * Render Create Table Page
     */
    private function renderCreateTable() {
        ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Create New Table</h1>
                <p class="text-gray-600">Add a new content type to your CMS by creating a custom table with fields.</p>
            </div>

            <!-- Create Table Form -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="create_table">
                    
                    <!-- Table Name -->
                    <div>
                        <label for="table_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Table Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="table_name" name="table_name" required
                               placeholder="e.g., products, events, testimonials"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-2">Use lowercase letters and underscores only (e.g., blog_posts)</p>
                    </div>

                    <!-- Table Builder -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Table Fields <span class="text-red-500">*</span>
                        </label>
                        <div class="table-builder bg-gray-50 border border-gray-300 rounded-lg p-6">
                            <div class="space-y-4" id="fieldsContainer">
                                <!-- Default fields will be added here by JavaScript -->
                            </div>
                            
                            <button type="button" onclick="addField(document.querySelector('.table-builder'))" 
                                    class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Add Field
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex space-x-4">
                        <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Table
                        </button>
                        <a href="?" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Field Types Help -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">📚 Field Types Reference</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <h4 class="font-medium text-blue-700 mb-2">Text Fields</h4>
                        <ul class="text-sm text-blue-600 space-y-1">
                            <li><strong>text:</strong> Short text (titles, names)</li>
                            <li><strong>textarea:</strong> Long text with WYSIWYG</li>
                            <li><strong>email:</strong> Email addresses</li>
                            <li><strong>url:</strong> Website links</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-blue-700 mb-2">Numbers & Dates</h4>
                        <ul class="text-sm text-blue-600 space-y-1">
                            <li><strong>number:</strong> Numeric values</li>
                            <li><strong>date:</strong> Date picker</li>
                            <li><strong>datetime:</strong> Date and time</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-blue-700 mb-2">Media & Relations</h4>
                        <ul class="text-sm text-blue-600 space-y-1">
                            <li><strong>media_single:</strong> One media file</li>
                            <li><strong>media_multiple:</strong> Multiple files</li>
                            <li><strong>foreign_key:</strong> Link to other table</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Initialize with one empty field
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('fieldsContainer');
            
            // Add one empty field by default
            addFieldToContainer(container);
        });

        function addFieldToContainer(container, name = '', type = 'text') {
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'field-row grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-white border border-gray-200 rounded-lg';
            fieldDiv.innerHTML = `
                <div>
                    <input type="text" name="fields[name][]" value="${name}" placeholder="Field name (e.g., title)" required
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <select name="fields[type][]" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="text" ${type === 'text' ? 'selected' : ''}>Text</option>
                        <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>Textarea (Long Text)</option>
                        <option value="email" ${type === 'email' ? 'selected' : ''}>Email</option>
                        <option value="url" ${type === 'url' ? 'selected' : ''}>URL</option>
                        <option value="number" ${type === 'number' ? 'selected' : ''}>Number</option>
                        <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Boolean (Yes/No)</option>
                        <option value="date" ${type === 'date' ? 'selected' : ''}>Date</option>
                        <option value="datetime" ${type === 'datetime' ? 'selected' : ''}>Date & Time</option>
                        <option value="media_single" ${type === 'media_single' ? 'selected' : ''}>Media (Single File)</option>
                        <option value="media_multiple" ${type === 'media_multiple' ? 'selected' : ''}>Media (Multiple Files)</option>
                        <option value="foreign_key" ${type === 'foreign_key' ? 'selected' : ''}>Foreign Key</option>
                    </select>
                </div>
                <div class="flex items-center">
                    <button type="button" onclick="removeField(this)" 
                            class="px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors text-sm">
                        Remove
                    </button>
                </div>
            `;
            container.appendChild(fieldDiv);
        }

        function addField(tableBuilder) {
            const container = tableBuilder.querySelector('#fieldsContainer');
            addFieldToContainer(container);
        }

        function removeField(button) {
            button.closest('.field-row').remove();
        }
        </script>
        <?php
    }
    
    /**
     * Enhanced form field rendering
     */
    private function renderForeignKeyTableCell($foreignTable, $displayColumn, $value) {
        if (!$value || !$foreignTable) {
            echo '<span class="text-gray-500 text-sm">None</span>';
            return;
        }
        
        $foreignRecord = $this->getForeignRecordById($foreignTable, $value);
        if ($foreignRecord) {
            $displayValue = $this->getDisplayValueForRecord($foreignRecord, $displayColumn);
            ?>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6H12L10,4Z"/>
                    </svg>
                    <?= htmlspecialchars($displayValue) ?>
                </span>
                <span class="text-xs text-gray-500">in <?= htmlspecialchars($foreignTable) ?></span>
            </div>
            <?php
        } else {
            echo '<span class="text-red-500 text-sm">Record not found</span>';
        }
    }

    private function renderMediaTableCell($fieldName, $mediaType, $value) {
        if ($mediaType === 'single' && $value) {
            $media = $this->getMediaById($value);
            if ($media) {
                $isImage = in_array(strtolower(pathinfo($media['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                ?>
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                        <?php if ($isImage): ?>
                            <img src="<?= htmlspecialchars($media['path']) ?>" 
                                 alt="<?= htmlspecialchars($media['original_filename']) ?>"
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate" title="<?= htmlspecialchars($media['original_filename']) ?>">
                            <?= htmlspecialchars($media['original_filename']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= number_format($media['file_size'] / 1024, 1) ?> KB
                        </p>
                    </div>
                </div>
                <?php
            } else {
                echo '<span class="text-red-500 text-sm">Media not found</span>';
            }
        } elseif ($mediaType === 'multiple' && $value) {
            $mediaIds = json_decode($value, true) ?: [];
            if (!empty($mediaIds)) {
                ?>
                <div class="space-y-2">
                    <div class="flex -space-x-2">
                        <?php foreach (array_slice($mediaIds, 0, 4) as $index => $mediaId): ?>
                            <?php $media = $this->getMediaById($mediaId); ?>
                            <?php if ($media): ?>
                                <?php $isImage = in_array(strtolower(pathinfo($media['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']); ?>
                                <div class="w-8 h-8 bg-gray-100 rounded border-2 border-white overflow-hidden" 
                                     title="<?= htmlspecialchars($media['original_filename']) ?>">
                                    <?php if ($isImage): ?>
                                        <img src="<?= htmlspecialchars($media['path']) ?>" 
                                             alt="<?= htmlspecialchars($media['original_filename']) ?>"
                                             class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (count($mediaIds) > 4): ?>
                            <div class="w-8 h-8 bg-gray-200 rounded border-2 border-white flex items-center justify-center">
                                <span class="text-xs text-gray-600">+<?= count($mediaIds) - 4 ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500">
                        <?= count($mediaIds) ?> file<?= count($mediaIds) !== 1 ? 's' : '' ?>
                    </p>
                </div>
                <?php
            } else {
                echo '<span class="text-gray-500 text-sm">No media</span>';
            }
        } else {
            echo '<span class="text-gray-500 text-sm">No media</span>';
        }
    }

    private function renderForeignKeyField($fieldName, $foreignTable, $displayColumn, $value) {
        if (!$foreignTable) {
            ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">
                    <strong>Foreign table not found.</strong> 
                    Make sure the target table exists and the field name follows the pattern "tablename_id".
                </p>
            </div>
            <?php
            return;
        }
        
        $foreignRecords = $this->getForeignTableRecords($foreignTable);
        
        if (empty($foreignRecords)) {
            ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">
                    <strong>No records in <?= htmlspecialchars($foreignTable) ?> table.</strong> 
                    <a href="?table=<?= urlencode($foreignTable) ?>" class="text-yellow-900 underline">Create some <?= htmlspecialchars($foreignTable) ?> records first</a> to link them here.
                </p>
            </div>
            <?php
            return;
        }
        
        ?>
        <select name="<?= htmlspecialchars($fieldName) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">-- Select <?= htmlspecialchars(ucfirst(rtrim($foreignTable, 's'))) ?> --</option>
            <?php foreach ($foreignRecords as $record): ?>
                <option value="<?= $record['id'] ?>" <?= $value == $record['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($this->getDisplayValueForRecord($record, $displayColumn)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1">
            Links to records in the <?= htmlspecialchars($foreignTable) ?> table
        </p>
        <?php
    }

    private function renderMediaField($fieldName, $mediaType, $value, $allMedia) {
        $totalMediaCount = $this->getTotalMediaCount();
        if ($totalMediaCount === 0) {
            ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">
                    <strong>No media files available.</strong> 
                    <a href="?table=media" class="text-yellow-900 underline">Upload some media files first</a> to link them to this field.
                </p>
            </div>
            <?php
            return;
        }
        
        // Get current selected media info for display
        $selectedMedia = null;
        $selectedMediaIds = [];
        
        if ($mediaType === 'single' && $value) {
            $selectedMedia = $this->getMediaById($value);
        } elseif ($mediaType === 'multiple' && $value) {
            $selectedMediaIds = json_decode($value, true) ?: [];
        }
        
        ?>
        <div class="space-y-3">
            <!-- Hidden input to store the selected value(s) -->
            <input type="hidden" name="<?= htmlspecialchars($fieldName) ?>" id="<?= htmlspecialchars($fieldName) ?>_input" value="<?= htmlspecialchars($value) ?>">
            
            <!-- Media selector button -->
            <button type="button" onclick="openMediaSelector('<?= htmlspecialchars($fieldName) ?>', '<?= $mediaType ?>')" 
                    class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-400 hover:bg-blue-50 transition-colors text-center">
                <div class="flex items-center justify-center space-x-2 text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>
                        <?php if ($mediaType === 'single'): ?>
                            <?= $selectedMedia ? 'Change Media File' : 'Select Media File' ?>
                        <?php else: ?>
                            <?= !empty($selectedMediaIds) ? 'Change Media Files' : 'Select Media Files' ?>
                        <?php endif; ?>
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Browse <?= number_format($totalMediaCount) ?> files with search and filtering
                </p>
            </button>
            
            <!-- Selected media preview -->
            <div id="<?= htmlspecialchars($fieldName) ?>_preview">
                <?php if ($mediaType === 'single' && $selectedMedia): ?>
                    <?php $this->renderSelectedMediaPreview($selectedMedia, true); ?>
                <?php elseif ($mediaType === 'multiple' && !empty($selectedMediaIds)): ?>
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-gray-700"><?= count($selectedMediaIds) ?> file(s) selected:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (array_slice($selectedMediaIds, 0, 5) as $mediaId): ?>
                                <?php $media = $this->getMediaById($mediaId); ?>
                                <?php if ($media): ?>
                                    <?php $this->renderSelectedMediaPreview($media, false); ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (count($selectedMediaIds) > 5): ?>
                                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center border">
                                    <span class="text-xs text-gray-600">+<?= count($selectedMediaIds) - 5 ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function getOriginalFieldType($tableName, $fieldName) {
        try {
            $stmt = $this->db->prepare("SELECT field_type FROM table_field_meta WHERE table_name = ? AND field_name = ?");
            $stmt->execute([$tableName, $fieldName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['field_type'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function renderFormField($field, $value, $tableName = null) {
        $name = htmlspecialchars($field['name']);
        $value = htmlspecialchars($value);
        $baseClasses = "w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent";
        
        // Get original field type from metadata if available
        $originalFieldType = $tableName ? $this->getOriginalFieldType($tableName, $field['name']) : null;
        $fieldTypeToCheck = $originalFieldType ?: strtolower($field['type']);
        
        switch ($fieldTypeToCheck) {
            case 'integer':
            case 'real':
                echo "<input type='number' id='{$name}' name='{$name}' value='{$value}' class='{$baseClasses}' step='any'>";
                break;
                
            case 'date':
                echo "<input type='text' id='{$name}' name='{$name}' value='{$value}' class='{$baseClasses} flatpickr-date' placeholder='Select date...'>";
                break;
                
            case 'datetime':
                $datetimeValue = $value ? date('Y-m-d H:i', strtotime($value)) : '';
                echo "<input type='text' id='{$name}' name='{$name}' value='{$datetimeValue}' class='{$baseClasses} flatpickr-datetime' placeholder='Select date and time...'>";
                break;
                
            case 'boolean':
                $checked = $value ? 'checked' : '';
                echo "<div class='flex items-center'>";
                echo "<input type='checkbox' id='{$name}' name='{$name}' value='1' {$checked} class='w-5 h-5 text-blue-600 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500'>";
                echo "<label for='{$name}' class='ml-3 text-sm text-gray-700'>Check if yes</label>";
                echo "</div>";
                break;
                
            case 'textarea':
                $this->renderWysiwygField($name, $value);
                break;
                
            default:
                if (strlen($value) > 100) {
                    $this->renderWysiwygField($name, $value);
                } else {
                    echo "<input type='text' id='{$name}' name='{$name}' value='{$value}' class='{$baseClasses}' placeholder='Enter " . str_replace('_', ' ', $name) . "...'>";
                }
                break;
        }
    }
    
    private function renderWysiwygField($name, $value) {
        $editorId = $name . '_editor';
        $hiddenId = $name . '_hidden';
        ?>
        <div class="wysiwyg-container">
            <!-- Toolbar -->
            <div class="wysiwyg-toolbar border border-gray-300 rounded-t-lg bg-gray-50 p-2 flex items-center space-x-2 flex-wrap">
                <button type="button" onclick="formatText(this, 'bold')" class="wysiwyg-btn" title="Bold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M15.6,10.79C16.57,10.11 17.25,9.02 17.25,8C17.25,5.74 15.5,4 13.25,4H7V18H14.04C16.14,18 17.75,16.3 17.75,14.21C17.75,12.69 16.89,11.39 15.6,10.79M10,6.5H13C13.83,6.5 14.5,7.17 14.5,8C14.5,8.83 13.83,9.5 13,9.5H10V6.5M14,15.5H10V12.5H14C14.83,12.5 15.5,13.17 15.5,14C15.5,14.83 14.83,15.5 14,15.5Z"/>
                    </svg>
                </button>
                <button type="button" onclick="formatText(this, 'italic')" class="wysiwyg-btn" title="Italic">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10,4V7H12.21L8.79,15H6V18H14V15H11.79L15.21,7H18V4H10Z"/>
                    </svg>
                </button>
                <button type="button" onclick="formatText(this, 'underline')" class="wysiwyg-btn" title="Underline">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M5,21H19V19H5V21M12,17A6,6 0 0,0 18,11V3H15.5V11A3.5,3.5 0 0,1 12,14.5A3.5,3.5 0 0,1 8.5,11V3H6V11A6,6 0 0,0 12,17Z"/>
                    </svg>
                </button>
                <div class="border-l border-gray-300 h-6 mx-2"></div>
                <button type="button" onclick="formatText(this, 'insertUnorderedList')" class="wysiwyg-btn" title="Bullet List">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z"/>
                    </svg>
                </button>
                <button type="button" onclick="formatText(this, 'insertOrderedList')" class="wysiwyg-btn" title="Numbered List">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7,13V11H21V13H7M7,19V17H21V19H7M7,7V5H21V7H7M3,8V5H2V4H4V8H3M2,17V16H5V20H2V19H4V18.5H3V17.5H4V17H2M4.25,10A0.75,0.75 0 0,1 5,10.75C5,10.95 4.92,11.14 4.79,11.27L3.12,13H5V14H2V13.08L4,11H2V10H4.25Z"/>
                    </svg>
                </button>
                <div class="border-l border-gray-300 h-6 mx-2"></div>
                <button type="button" onclick="formatText(this, 'createLink')" class="wysiwyg-btn" title="Insert Link">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z"/>
                    </svg>
                </button>
                <button type="button" onclick="formatText(this, 'removeFormat')" class="wysiwyg-btn" title="Clear Formatting">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6,5V5.18L8.82,8H11.22L10.5,9.68L12.6,11.78C13.25,10.8 13.24,9.54 12.65,8.5C12.26,7.78 11.62,7.26 10.86,7.05L14.3,5.18C14.5,5 14.9,5 15.1,5.18C15.3,5.4 15.3,5.8 15.1,6L12.89,7.89C13.0,7.9 13.1,7.9 13.22,7.9C14.22,8 15.22,8.4 16.22,9.4C17.33,10.5 17.44,12.18 16.55,13.4L19.77,16.62L18.36,18.03L2,1.67L3.41,0.25L6,2.84V5M7.91,10.09L6,8.18V10.09H7.91M6,14H10.36L7.91,11.55H6V14Z"/>
                    </svg>
                </button>
            </div>
            
            <!-- Editor -->
            <div id="<?= $editorId ?>" 
                 contenteditable="true" 
                 class="wysiwyg-editor border-l border-r border-b border-gray-300 rounded-b-lg p-4 min-h-32 max-h-64 overflow-y-auto focus:outline-none focus:ring-2 focus:ring-blue-500"
                 style="white-space: pre-wrap;"
                 placeholder="Enter <?= str_replace('_', ' ', $name) ?>..."
                 oninput="updateHiddenField(this)"><?= htmlspecialchars_decode($value) ?></div>
            
            <!-- Hidden field for form submission -->
            <input type="hidden" id="<?= $hiddenId ?>" name="<?= $name ?>" value="<?= htmlspecialchars($value) ?>" class="wysiwyg-hidden">
        </div>
        
        <style>
            .wysiwyg-btn {
                padding: 6px 8px;
                border: 1px solid #d1d5db;
                background: white;
                border-radius: 4px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            .wysiwyg-btn:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
            }
            .wysiwyg-btn:active {
                background: #e5e7eb;
            }
            .wysiwyg-editor:empty:before {
                content: attr(placeholder);
                color: #9ca3af;
                font-style: italic;
            }
            .wysiwyg-editor:focus {
                border-color: #3b82f6;
            }
            .wysiwyg-editor p {
                margin: 0 0 1em 0;
            }
            .wysiwyg-editor ul, .wysiwyg-editor ol {
                margin: 0 0 1em 0;
                padding-left: 2em;
            }
            .wysiwyg-editor li {
                margin: 0.25em 0;
            }
            .wysiwyg-editor a {
                color: #3b82f6;
                text-decoration: underline;
            }
        </style>
        
        <script>
            
            
            // Initialize editor on page load
            document.addEventListener('DOMContentLoaded', function() {
                const editors = document.querySelectorAll('.wysiwyg-editor');
                editors.forEach(function(editor) {
                    // Format existing content
                    if (editor.innerHTML.trim()) {
                        editor.innerHTML = editor.innerHTML.replace(/\n/g, '<br>');
                    }
                });
                
                // Initialize WYSIWYG editors in the first visible tab
                const firstVisibleTab = document.querySelector('.tab-content:not(.hidden)');
                if (firstVisibleTab) {
                    initializeWysiwygInTab(firstVisibleTab);
                }
            });
        </script>
        <?php
    }
}

// Initialize and run the CMS
$cms = new ModernCMS();
$cms->render();
?>
