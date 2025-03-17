<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php'; // For PHPMailer and TCPDF
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client; // Add this line for Twilio
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Add these checks at the top of your file
if (!extension_loaded('openssl')) {
    error_log("OpenSSL PHP extension is not loaded");
}
if (!extension_loaded('mbstring')) {
    error_log("mbstring PHP extension is not loaded");
}

/**
 * Generate QR code for booking
 * 
 * @param array $booking Booking details
 * @return string Path to generated QR code
 */
function generateBookingQR($booking) {
    // Create directory if it doesn't exist
    $dir = 'uploads/qrcodes';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    try {
        // QR code data
        $qrData = json_encode([
            'booking_id' => $booking['booking_id'],
            'visit_date' => $booking['visit_date'],
            'total_tickets' => $booking['adult_tickets'] + $booking['child_0_5_tickets'] + 
                             $booking['child_5_12_tickets'] + $booking['senior_tickets']
        ]);

        // Generate QR code using Endroid library
        $result = Builder::create()
            ->data($qrData)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->writer(new PngWriter())
            ->build();

        // Save QR code
        $qr_file = $dir . '/booking_' . $booking['booking_id'] . '.png';
        $result->saveToFile($qr_file);

        return $qr_file;
    } catch (Exception $e) {
        error_log("Error generating QR code: " . $e->getMessage());
        return 'images/default-qr.png';
    }
}

/**
 * Generate e-ticket PDF
 * 
 * @param array $booking Booking details
 * @return string Path to generated PDF
 */
function generateETicket($booking) {
    // Create directory if it doesn't exist
    $dir = 'uploads/tickets';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('SafariGate Zoo');
        $pdf->SetAuthor('SafariGate Zoo');
        $pdf->SetTitle('Zoo E-Ticket #' . $booking['booking_id']);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Add logo if exists
        if (file_exists('images/logo.png')) {
            $pdf->Image('images/logo.png', 10, 10, 40);
        }
        
        // Add QR code
        $qr_path = generateBookingQR($booking);
        $pdf->Image($qr_path, 150, 10, 40);
        
        // Add ticket content with improved styling
        $html = '
            <style>
                h1 { color: #ff6e01; font-size: 24pt; }
                .info { color: #666666; font-size: 12pt; }
                .important { color: #ff0000; font-size: 10pt; }
                .ticket-details { border: 1px solid #cccccc; padding: 10px; margin: 10px 0; }
            </style>
            <h1>SafariGate Zoo E-Ticket</h1>
            <div class="ticket-details">
                <p><strong>Booking #:</strong> ' . $booking['booking_id'] . '</p>
                <p><strong>Visit Date:</strong> ' . date('F j, Y', strtotime($booking['visit_date'])) . '</p>
                <p><strong>Visitor Name:</strong> ' . htmlspecialchars($booking['name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($booking['email']) . '</p>
                <p><strong>Phone:</strong> ' . htmlspecialchars($booking['phone']) . '</p>
                <hr>
                <h2>Ticket Details:</h2>';
        
        if ($booking['adult_tickets'] > 0) {
            $html .= '<p>Adult Tickets: ' . $booking['adult_tickets'] . '</p>';
        }
        if ($booking['child_0_5_tickets'] > 0) {
            $html .= '<p>Child (0-5) Tickets: ' . $booking['child_0_5_tickets'] . '</p>';
        }
        if ($booking['child_5_12_tickets'] > 0) {
            $html .= '<p>Child (5-12) Tickets: ' . $booking['child_5_12_tickets'] . '</p>';
        }
        if ($booking['senior_tickets'] > 0) {
            $html .= '<p>Senior Tickets: ' . $booking['senior_tickets'] . '</p>';
        }
        
        $html .= '
                <p><strong>Total Amount:</strong> ₹' . number_format($booking['total_amount'], 2) . '</p>
            </div>
            <div class="important">
                <p>Important Information:</p>
                <ul>
                    <li>Zoo Hours: 9:00 AM - 5:00 PM (Last entry at 4:00 PM)</li>
                    <li>Please bring a valid photo ID for verification</li>
                    <li>Children below 12 years must be accompanied by an adult</li>
                    <li>This ticket is valid only for the date shown above</li>
                </ul>
            </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save PDF
        $pdf_file = $dir . '/ticket_' . $booking['booking_id'] . '.pdf';
        $pdf->Output($pdf_file, 'F');
        
        return $pdf_file;
    } catch (Exception $e) {
        error_log("Error generating e-ticket: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Send email confirmation with e-ticket
 * 
 * @param array $booking Booking details
 * @return boolean Success status
 */
function sendBookingConfirmationEmail($booking) {
    error_log("Starting email sending process for booking ID: " . $booking['booking_id']);
    
    try {
        // Initialize PHPMailer with debug mode
        $mail = new PHPMailer(true);
        
        // Enable VERY detailed debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer ($level): $str");
        };
        
        // Server settings with more detailed error handling
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        
        // Update Gmail credentials to match send-otp.php
        $mail->Username = 'sherinmariyamvarghese@gmail.com';
        $mail->Password = 'uhfo rkzj aewm kdpb';
        
        // Enable TLS explicitly
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Set longer timeout
        $mail->Timeout = 60;
        
        // Set sender and recipient with error checking
        error_log("Setting up email addresses - From: sherinmariyamvarghese@gmail.com, To: " . $booking['email']);
        $mail->setFrom('sherinmariyamvarghese@gmail.com', 'SafariGate Zoo');
        $mail->addAddress($booking['email'], $booking['name']);
        
        // Generate and attach e-ticket
        try {
            $ticket_path = generateETicket($booking);
            if ($ticket_path && file_exists($ticket_path)) {
                $mail->addAttachment($ticket_path, 'e-ticket.pdf');
                error_log("E-ticket attached successfully: " . $ticket_path);
            } else {
                error_log("Failed to attach e-ticket: " . $ticket_path);
            }
        } catch (Exception $e) {
            error_log("Error generating e-ticket: " . $e->getMessage());
        }

        // Set email content
        $mail->isHTML(true);
        $mail->Subject = 'SafariGate Zoo - Booking Confirmation #' . $booking['booking_id'];
        
        // Create email body
        $messageBody = "
            <h2>Thank you for your booking!</h2>
            <p>Dear {$booking['name']},</p>
            <p>Your booking has been confirmed. Here are your booking details:</p>
            <ul>
                <li>Booking ID: {$booking['booking_id']}</li>
                <li>Visit Date: " . date('F j, Y', strtotime($booking['visit_date'])) . "</li>
                <li>Number of Tickets:
                    <ul>";
        
        if ($booking['adult_tickets'] > 0) {
            $messageBody .= "<li>Adult Tickets: {$booking['adult_tickets']}</li>";
        }
        if ($booking['child_0_5_tickets'] > 0) {
            $messageBody .= "<li>Child (0-5) Tickets: {$booking['child_0_5_tickets']}</li>";
        }
        if ($booking['child_5_12_tickets'] > 0) {
            $messageBody .= "<li>Child (5-12) Tickets: {$booking['child_5_12_tickets']}</li>";
        }
        if ($booking['senior_tickets'] > 0) {
            $messageBody .= "<li>Senior Tickets: {$booking['senior_tickets']}</li>";
        }
        
        $messageBody .= "
                    </ul>
                </li>
                <li>Total Amount: ₹" . number_format($booking['total_amount'], 2) . "</li>
            </ul>
            <p>Please show this email and a valid ID proof at the entrance.</p>
            <p>Important Information:</p>
            <ul>
                <li>Zoo Hours: 9:00 AM - 5:00 PM</li>
                <li>Last Entry: 4:00 PM</li>
                <li>Children below 12 years must be accompanied by an adult</li>
            </ul>
            <p>We look forward to your visit!</p>
            <p>Best regards,<br>SafariGate Zoo Team</p>
        ";
        
        $mail->Body = $messageBody;
        $mail->AltBody = strip_tags($messageBody);
        
        // Test SMTP connection before sending
        if (!$mail->smtpConnect()) {
            throw new Exception("Failed to connect to SMTP server: " . $mail->ErrorInfo);
        }
        
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        
        error_log("Email sent successfully to: " . $booking['email']);
        return true;
        
    } catch (Exception $e) {
        error_log("CRITICAL ERROR in email sending: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send all booking confirmations
 * 
 * @param array $booking Booking details
 * @return array Status of notifications
 */
function sendBookingConfirmations($booking) {
    $status = [
        'email' => false
    ];
    
    try {
        // Send email
        $status['email'] = sendBookingConfirmationEmail($booking);
        
        // Log the overall status
        error_log("Booking confirmations sent - Email: " . ($status['email'] ? 'Success' : 'Failed'));
                 
    } catch (Exception $e) {
        error_log("Error in sendBookingConfirmations: " . $e->getMessage());
    }
    
    return $status;
}

function processBookingConfirmation($booking_details) {
    $status = ['email' => false, 'sms' => false];
    
    try {
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';  // Updated to Gmail SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'sherinmariyamvarghese@gmail.com';
        $mail->Password = 'uhfo rkzj aewm kdpb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Enable debug mode
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer ($level): $str");
        };

        // Recipients
        $mail->setFrom('sherinmariyamvarghese@gmail.com', 'SafariGate Zoo');
        $mail->addAddress($booking_details['email'], $booking_details['name']);

        // Attach PDF bill if it exists
        if (isset($booking_details['bill_pdf_path']) && file_exists($booking_details['bill_pdf_path'])) {
            $mail->addAttachment($booking_details['bill_pdf_path'], 'SafariGate_Zoo_Invoice.pdf');
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - SafariGate Zoo';
        $mail->Body = generateEmailTemplate($booking_details);

        $mail->send();
        $status['email'] = true;
        error_log("Email sent successfully to: " . $booking_details['email']);
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }

    return $status;
}

function generateEmailTemplate($booking_details) {
    // Create HTML email template
    $html = "
    <h2>Thank you for your booking!</h2>
    <p>Dear {$booking_details['name']},</p>
    <p>Your booking has been confirmed. Please find your booking details below:</p>
    <ul>
        <li>Booking ID: {$booking_details['booking_id']}</li>
        <li>Visit Date: " . date('d/m/Y', strtotime($booking_details['visit_date'])) . "</li>
        <li>Total Amount: ₹" . number_format($booking_details['total_amount'], 2) . "</li>
    </ul>
    <p>Please find your invoice attached to this email.</p>
    ";
    
    return $html;
}

// Only process if this file is included, not directly accessed
if (isset($booking_details)) {
    if (!isset($_SESSION)) {
        session_start();
    }
    $notification_status = processBookingConfirmation($booking_details);
    $_SESSION['notification_status'] = $notification_status;
} 