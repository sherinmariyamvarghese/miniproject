<?php
session_start();
require_once 'connect.php';

if (!isset($_GET['booking_id'])) {
    die('Booking ID not provided');
}

$booking_id = intval($_GET['booking_id']);

// Fetch booking details from database
$stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die('Booking not found');
}

// Generate or fetch existing ticket
require_once 'booking_confirmation.php';
try {
    $ticket_path = generateETicket($booking);
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="SafariGate_Zoo_Ticket_' . $booking_id . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    
    readfile($ticket_path);
} catch (Exception $e) {
    die('Error generating ticket: ' . $e->getMessage());
} 