<?php
require_once 'vendor/autoload.php';

function generateBillPDF($booking_details) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('SafariGate Zoo');
    $pdf->SetAuthor('SafariGate Zoo');
    $pdf->SetTitle('Booking Invoice #' . $booking_details['booking_id']);

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Logo
    $pdf->Image('path/to/logo.png', 15, 10, 30);

    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 20, 'SafariGate Zoo - Booking Invoice', 0, 1, 'C');
    
    // Invoice details
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Invoice #: ' . $booking_details['booking_id'], 0, 1);
    $pdf->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 1);
    $pdf->Cell(0, 10, 'Visit Date: ' . date('d/m/Y', strtotime($booking_details['visit_date'])), 0, 1);

    // Customer details
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Customer Details', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Name: ' . $booking_details['name'], 0, 1);
    $pdf->Cell(0, 10, 'Email: ' . $booking_details['email'], 0, 1);
    $pdf->Cell(0, 10, 'Phone: ' . $booking_details['phone'], 0, 1);
    $pdf->Cell(0, 10, 'Address: ' . $booking_details['address'], 0, 1);

    // Booking details
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(100, 10, 'Item', 1);
    $pdf->Cell(30, 10, 'Quantity', 1);
    $pdf->Cell(60, 10, 'Amount (₹)', 1);
    $pdf->Ln();

    // Table content
    $pdf->SetFont('helvetica', '', 12);
    if ($booking_details['adult_tickets'] > 0) {
        $pdf->Cell(100, 10, 'Adult Tickets', 1);
        $pdf->Cell(30, 10, $booking_details['adult_tickets'], 1);
        $pdf->Cell(60, 10, number_format($booking_details['adult_tickets'] * 80, 2), 1);
        $pdf->Ln();
    }
    // Add similar entries for other ticket types
    
    // Total
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(130, 10, 'Total Amount', 1);
    $pdf->Cell(60, 10, '₹' . number_format($booking_details['total_amount'], 2), 1);

    // Payment details
    $pdf->Ln(15);
    $pdf->Cell(0, 10, 'Payment ID: ' . $booking_details['razorpay_payment_id'], 0, 1);
    $pdf->Cell(0, 10, 'Payment Status: Completed', 0, 1);

    // Save PDF
    $filename = 'bills/invoice_' . $booking_details['booking_id'] . '.pdf';
    $pdf->Output(dirname(__FILE__) . '/' . $filename, 'F');
    
    return $filename;
} 