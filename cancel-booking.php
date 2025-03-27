<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and booking ID is provided
if (!isset($_SESSION['logged_in']) || !isset($_GET['id'])) {
    header('Location: my-bookings.php');
    exit();
}

$booking_id = $_GET['id'];

try {
    $conn->begin_transaction();

    // Get booking details first
    $stmt = $conn->prepare("
        SELECT visit_date, adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets as total_tickets 
        FROM bookings 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception("Booking not found or already cancelled");
    }

    // Update booking status to cancelled
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'cancelled', 
            cancelled_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $booking_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to cancel booking");
    }

    // Update available tickets in daily_tickets table
    $stmt = $conn->prepare("
        UPDATE daily_tickets 
        SET booked_tickets = booked_tickets - ? 
        WHERE date = ?
    ");
    $stmt->bind_param("is", $booking['total_tickets'], $booking['visit_date']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update ticket availability");
    }

    $conn->commit();
    $_SESSION['success'] = "Booking cancelled successfully.";
    header('Location: my-bookings.php');

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: my-bookings.php');
}
exit();
?> 