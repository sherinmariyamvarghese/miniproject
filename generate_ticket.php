<?php
session_start();
require_once 'booking_confirmation.php';

if (!isset($_GET['booking_id']) || !isset($_SESSION['booking_data'])) {
    header('Location: booking.php');
    exit;
}

$booking_id = $_GET['booking_id'];
if ($_SESSION['booking_data']['booking_id'] != $booking_id) {
    header('Location: booking.php');
    exit;
}

try {
    // Generate the e-ticket
    $ticket_path = generateETicket($_SESSION['booking_data']);
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="SafariGate_Zoo_Ticket_' . $booking_id . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($ticket_path);
    exit;
    
} catch (Exception $e) {
    error_log("Error generating ticket for download: " . $e->getMessage());
    header('Location: booking_success.php');
    exit;
}
?> 