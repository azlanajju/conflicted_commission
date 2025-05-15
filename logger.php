<?php
/**
 * Logger class for handling commission update logs
 */
class CommissionLogger {
    private $conn;
    private $logFile;
    private $logToDatabase;
    private $logToFile;

    /**
     * Constructor
     * 
     * @param PDO $dbConnection Database connection
     * @param bool $logToDatabase Whether to log to database (default: true)
     * @param bool $logToFile Whether to log to file (default: true)
     * @param string $logFilePath Path to log file (default: 'logs/commission_updates.log')
     */
    public function __construct($dbConnection, $logToDatabase = true, $logToFile = true, $logFilePath = 'logs/commission_updates.log') {
        $this->conn = $dbConnection;
        $this->logToDatabase = $logToDatabase;
        $this->logToFile = $logToFile;
        $this->logFile = $logFilePath;
        
        // Create logs directory if it doesn't exist
        if ($logToFile && !is_dir(dirname($logFilePath))) {
            mkdir(dirname($logFilePath), 0755, true);
        }
    }

    /**
     * Log commission update to database and/or file
     * 
     * @param array $data Log data
     * @return bool Success status
     */
    public function logCommissionUpdate($data) {
        $success = true;
        
        // Log to database if enabled
        if ($this->logToDatabase) {
            try {
                $insertQuery = "INSERT INTO CommissionUpdateLogs 
                                (ChildPromoterID, ChildPromoterName, OldChildCommission, 
                                NewChildCommission, OldParentCommission, NewParentCommission, 
                                UpdatedBy, IPAddress, Notes) 
                                VALUES 
                                (:childId, :childName, :oldChildComm, 
                                :newChildComm, :oldParentComm, :newParentComm, 
                                :updatedBy, :ipAddress, :notes)";
                
                $stmt = $this->conn->prepare($insertQuery);
                $stmt->bindParam(':childId', $data['childId']);
                $stmt->bindParam(':childName', $data['childName']);
                $stmt->bindParam(':oldChildComm', $data['oldChildCommission']);
                $stmt->bindParam(':newChildComm', $data['newChildCommission']);
                $stmt->bindParam(':oldParentComm', $data['oldParentCommission']);
                $stmt->bindParam(':newParentComm', $data['newParentCommission']);
                $stmt->bindParam(':updatedBy', $data['updatedBy']);
                $stmt->bindParam(':ipAddress', $data['ipAddress']);
                $stmt->bindParam(':notes', $data['notes']);
                
                $success = $stmt->execute();
            } catch (PDOException $e) {
                $this->writeToErrorLog('Database Error: ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Log to file if enabled
        if ($this->logToFile) {
            $logMessage = $this->formatLogMessage($data);
            $success = $success && $this->writeToLog($logMessage);
        }
        
        return $success;
    }

    /**
     * Format log message for file logging
     * 
     * @param array $data Log data
     * @return string Formatted log message
     */
    private function formatLogMessage($data) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] COMMISSION UPDATE";
        $message .= " | Child ID: {$data['childId']}";
        $message .= " | Child Name: {$data['childName']}";
        $message .= " | Child Commission: {$data['oldChildCommission']} → {$data['newChildCommission']}";
        $message .= " | Parent Commission: {$data['oldParentCommission']} → {$data['newParentCommission']}";
        $message .= " | Updated By: {$data['updatedBy']}";
        $message .= " | IP: {$data['ipAddress']}";
        if (isset($data['notes']) && !empty($data['notes'])) {
            $message .= " | Notes: {$data['notes']}";
        }
        
        return $message . PHP_EOL;
    }

    /**
     * Write message to log file
     * 
     * @param string $message Message to log
     * @return bool Success status
     */
    private function writeToLog($message) {
        try {
            $handle = fopen($this->logFile, 'a');
            if ($handle) {
                fwrite($handle, $message);
                fclose($handle);
                return true;
            }
        } catch (Exception $e) {
            $this->writeToErrorLog('File Log Error: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Write error message to PHP error log
     * 
     * @param string $message Error message
     */
    private function writeToErrorLog($message) {
        error_log($message);
    }

    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    public static function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
} 