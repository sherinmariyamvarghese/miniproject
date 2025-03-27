<?php
require_once 'connect.php';

function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, "booking_errors.log");
}

if (isset($_GET['date'])) {
    $date = $_GET['date'];
    
    $conn->begin_transaction();
    try {
        // First ensure we have a record for this date
        $stmt = $conn->prepare("INSERT IGNORE INTO daily_tickets (date, max_tickets) 
                               SELECT ?, max_tickets_per_day 
                               FROM ticket_rates WHERE id = 1");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        
        // Get current availability with a lock
        $stmt = $conn->prepare("SELECT booked_tickets, max_tickets 
                              FROM daily_tickets 
                              WHERE date = ? 
                              FOR UPDATE");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if (!$row) {
            // Initialize daily_tickets record if it doesn't exist
            $stmt = $conn->prepare("INSERT INTO daily_tickets (date, max_tickets) 
                                  SELECT ?, max_tickets_per_day 
                                  FROM ticket_rates WHERE id = 1");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            
            // Re-fetch the row
            $stmt = $conn->prepare("SELECT booked_tickets, max_tickets 
                                  FROM daily_tickets 
                                  WHERE date = ? 
                                  FOR UPDATE");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
        }
        
        $available_tickets = $row['max_tickets'] - $row['booked_tickets'];
        
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'available_tickets' => $available_tickets,
            'max_tickets' => $row['max_tickets'],
            'booked_tickets' => $row['booked_tickets']
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        logError("Error in check_availability.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// If no date parameter, return error
header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['error' => 'Date parameter is required']);