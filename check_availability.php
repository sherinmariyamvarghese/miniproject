<?php
require_once 'connect.php';

if (isset($_GET['date'])) {
    $date = $_GET['date'];
    
    // Get max tickets per day
    $rates = $conn->query("SELECT max_tickets_per_day FROM ticket_rates WHERE id = 1")->fetch_assoc();
    $max_tickets = $rates['max_tickets_per_day'];
    
    // Get booked tickets for the date
    $stmt = $conn->prepare("SELECT SUM(adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) 
                           as total_booked FROM bookings 
                           WHERE visit_date = ? AND payment_status != 'failed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $booked_tickets = $result['total_booked'] ?? 0;
    $available_tickets = $max_tickets - $booked_tickets;
    
    header('Content-Type: application/json');
    echo json_encode([
        'available_tickets' => $available_tickets,
        'max_tickets' => $max_tickets,
        'booked_tickets' => $booked_tickets
    ]);
    exit();
}

// If no date parameter, return error
header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['error' => 'Date parameter is required']);