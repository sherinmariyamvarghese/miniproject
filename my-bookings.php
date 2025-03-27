<?php
session_start();
require_once 'connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Fetch user's bookings
$stmt = $conn->prepare("
    SELECT b.*,
           CONCAT(
               CASE WHEN b.adult_tickets > 0 THEN CONCAT(b.adult_tickets, ' Adult(s) ') ELSE '' END,
               CASE WHEN b.child_0_5_tickets > 0 THEN CONCAT(b.child_0_5_tickets, ' Child(0-5) ') ELSE '' END,
               CASE WHEN b.child_5_12_tickets > 0 THEN CONCAT(b.child_5_12_tickets, ' Child(5-12) ') ELSE '' END,
               CASE WHEN b.senior_tickets > 0 THEN CONCAT(b.senior_tickets, ' Senior(s)') ELSE '' END
           ) as ticket_details
    FROM bookings b
    WHERE b.email = (
        SELECT email 
        FROM users 
        WHERE username = ? 
        LIMIT 1
    )
    AND b.status != 'cancelled'
    ORDER BY b.visit_date DESC
");

$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Enhanced compact booking layout styles */
        .bookings-container {
            max-width: 1400px;
            padding: 15px;
            background-image: url('images/pattern-bg.jpg');
            background-size: cover;
            background-attachment: fixed;
            position: relative;
        }
        
        .bookings-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.85);
            z-index: 0;
        }
        
        .booking-content {
            position: relative;
            z-index: 1;
        }
        
        .booking-header {
            margin-bottom: 20px;
            padding: 15px 0;
            text-align: center;
            position: relative;
        }
        
        .booking-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 3px;
            background: var(--main);
            border-radius: 2px;
        }
        
        .booking-header h2 {
            font-size: 2.5rem;
            margin-bottom: 5px;
            color: var(--main);
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
        }
        
        .booking-header p {
            font-size: 1.2rem;
            color: #666;
        }
        
        /* Grid layout for more compact display */
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            position: relative;
            z-index: 1;
        }
        
        /* Compact card style with safari theme */
        .booking-card {
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            font-size: 0.95rem;
            background: white;
            border-left: 4px solid var(--main);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        /* Status-based coloring */
        .booking-card.status-today {
            border-left-color: #ff8f00;
        }
        
        .booking-card.status-completed {
            border-left-color: #28a745;
        }
        
        .booking-card.status-cancelled {
            border-left-color: #dc3545;
        }
        
        .booking-card.status-upcoming {
            border-left-color: #17a2b8;
        }
        
        /* Improve ticket stub and holes styling */
        .ticket-stub {
            background: var(--main);
            width: 15px;
            height: 100%;
            position: absolute;
            left: 0;
        }
        
        .ticket-holes {
            position: absolute;
            left: 7px;
            top: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            z-index: 1;
        }
        
        .ticket-hole {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            box-shadow: inset 0 2px 3px rgba(0,0,0,0.2);
        }
        
        /* Reduce padding throughout but maintain readability */
        .booking-content {
            padding: 15px 15px 15px 25px;
            gap: 15px;
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }
        
        /* More compact QR container */
        .qr-container {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            width: 120px;
            margin-right: 15px;
        }
        
        .qr-code {
            max-width: 100px;
            height: auto;
            padding: 8px;
            margin: 0 auto;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .qr-code.used,
        .qr-code.future {
            padding: 10px;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .qr-code.used {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .qr-code.future {
            background: linear-gradient(135deg, #e2f0fd, #d0e5f5);
            color: #0c5460;
        }
        
        .qr-code i {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .countdown-label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .show-qr-btn {
            padding: 6px 10px;
            font-size: 0.85rem;
            background: var(--main);
            color: white;
            border: none;
            border-radius: 4px;
            margin-top: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .show-qr-btn:hover {
            background: var(--main-dark);
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        
        /* More compact booking details */
        .booking-details {
            flex: 1;
            min-width: 200px;
        }
        
        .booking-date {
            font-size: 1.3rem;
            margin-bottom: 5px;
            color: #444;
            font-weight: 500;
        }
        
        .booking-date i {
            color: var(--main);
            margin-right: 5px;
        }
        
        .booking-number {
            font-size: 0.9rem;
            margin-bottom: 12px;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        .booking-number i {
            margin-right: 5px;
            color: #888;
        }
        
        /* More compact and readable ticket info */
        .ticket-info {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 10px;
        }
        
        .ticket-row {
            font-size: 0.95rem;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 8px;
            background: white;
            border-radius: 4px;
        }
        
        .ticket-row.total {
            background-color: rgba(var(--main-rgb), 0.1);
            font-weight: 600;
            color: var(--main-dark);
        }
        
        .total-amount {
            font-size: 1.2rem;
            margin-top: 10px;
            padding: 8px;
            border-top: 1px dashed #ddd;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
        
        .total-amount span:last-child {
            color: var(--main-dark);
        }
        
        /* More compact action buttons */
        .booking-actions {
            padding: 10px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .action-btn {
            padding: 6px 12px;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 4px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .cancel-btn {
            background-color: white;
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        
        .cancel-btn:hover {
            background-color: #fff5f5;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.2);
        }
        
        /* Enhanced accordion-style toggle for individual tickets */
        .individual-tickets-toggle {
            display: block;
            width: 100%;
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border: none;
            border-top: 1px solid #eee;
            cursor: pointer;
            font-size: 0.95rem;
            color: #444;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .individual-tickets-toggle:hover {
            background: #eaf7ee;
            color: var(--main);
        }
        
        .individual-tickets-toggle i {
            transition: transform 0.2s ease;
        }
        
        .individual-tickets-toggle.active i {
            transform: rotate(180deg);
        }
        
        /* More compact and attractive individual tickets */
        .individual-tickets {
            display: none; /* Hide by default */
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 15px;
            background: #f9f9f9;
        }
        
        .individual-tickets.show {
            display: grid;
        }
        
        .ticket {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            font-size: 0.9rem;
            position: relative;
        }
        
        .ticket-header {
            padding: 8px 10px;
            background: var(--main);
            color: white;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .ticket-type-adult .ticket-header {
            background: #4caf50;
        }
        
        .ticket-type-child_0_5 .ticket-header {
            background: #2196f3;
        }
        
        .ticket-type-child_5_12 .ticket-header {
            background: #ff9800;
        }
        
        .ticket-type-senior .ticket-header {
            background: #9c27b0;
        }
        
        .ticket-body {
            padding: 12px;
            display: flex;
            justify-content: space-between;
        }
        
        .ticket-qr {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .used-qr, .pending-qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            height: 100%;
            font-size: 0.75rem;
        }
        
        .used-qr i, .pending-qr i {
            font-size: 1.5rem;
            margin-bottom: 3px;
        }
        
        .ticket-info {
            flex: 1;
            padding: 0 0 0 10px;
            background: transparent;
            box-shadow: none;
            margin-bottom: 0;
        }
        
        .ticket-footer {
            padding: 8px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .ticket-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .ticket-btn {
            padding: 4px 8px;
            font-size: 0.8rem;
            border-radius: 3px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .ticket-btn-view {
            background: #f8f9fa;
            color: #444;
            border: 1px solid #ddd;
        }
        
        .ticket-btn-download {
            background: var(--main);
            color: white;
        }
        
        /* Make sure the status indicator is visible but compact */
        .ticket-status {
            padding: 3px 8px;
            font-size: 0.8rem;
            border-radius: 3px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .ticket-status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .ticket-status-used {
            background: #d4edda;
            color: #155724;
        }
        
        .ticket-status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Adjust visited overlay for more compact display */
        .visited-overlay {
            backdrop-filter: blur(2px);
        }
        
        .visited-stamp {
            padding: 10px;
            gap: 10px;
            background: white;
            border: 2px solid #28a745;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .stamp-circle {
            width: 30px;
            height: 30px;
            background: #28a745;
        }
        
        .stamp-circle i {
            font-size: 16px;
        }
        
        .stamp-text span {
            font-size: 18px;
            color: #28a745;
        }
        
        .stamp-text small {
            font-size: 12px;
            color: #666;
        }
        
        /* Add safari theme elements */
        .animal-category {
            background: linear-gradient(135deg, rgba(255, 107, 34, 0.9) 0%, rgba(255, 85, 0, 0.9) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            padding: 3px 8px;
            border-radius: 3px;
            color: white;
            display: inline-block;
        }
        
        /* Status today pulsing effect */
        .status-today {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 143, 0, 0.4);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(255, 143, 0, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 143, 0, 0);
            }
        }
        
        /* Media query adjustments */
        @media (min-width: 768px) {
            .bookings-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }
        
        @media (min-width: 1200px) {
            .bookings-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            .booking-content {
                flex-direction: column;
            }
            
            .qr-container {
                width: 100%;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .individual-tickets {
                grid-template-columns: 1fr;
            }
        }

        /* Update the ticket used state styling */
        .ticket.used {
            opacity: 0.95;
            position: relative;
            background: #f8f9fa;
        }

        .ticket.used::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                rgba(40, 167, 69, 0.1),
                rgba(40, 167, 69, 0.1) 10px,
                rgba(255, 255, 255, 0.5) 10px,
                rgba(255, 255, 255, 0.5) 20px
            );
            z-index: 1;
        }

        .visited-stamp-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(2px);
        }

        .visited-stamp-overlay span {
            color: #28a745;
            font-size: 2rem;
            font-weight: bold;
            transform: rotate(-15deg);
            border: 3px solid #28a745;
            padding: 8px 20px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
            text-shadow: 1px 1px 0 white;
        }

        /* Make sure content is still visible through the overlay */
        .ticket.used .ticket-header,
        .ticket.used .ticket-body,
        .ticket.used .ticket-footer {
            position: relative;
            z-index: 3;
        }

        .ticket.used .ticket-status-used {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            font-weight: bold;
        }

        /* Add a subtle border effect for used tickets */
        .ticket.used {
            border: 1px solid rgba(40, 167, 69, 0.3);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
        }

        /* Enhance the ticket type header for used tickets */
        .ticket.used .ticket-header {
            opacity: 0.8;
        }

        /* Style adjustments for the ticket info when used */
        .ticket.used .ticket-info {
            opacity: 0.8;
        }

        /* Style for the actions section in used tickets */
        .ticket.used .ticket-footer {
            background: rgba(248, 249, 250, 0.8);
            border-top: 1px solid rgba(40, 167, 69, 0.2);
        }

        /* Add hover effect for better interaction */
        .ticket.used:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
            transition: all 0.3s ease;
        }

        /* Make the "View QR" button more visible in used tickets */
        .ticket.used .ticket-btn-view {
            background: #28a745;
            color: white;
            border: none;
        }

        .ticket.used .ticket-btn-view:hover {
            background: #218838;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Add transition effects */
        .ticket {
            transition: all 0.3s ease;
        }

        .visited-stamp-overlay span {
            transition: transform 0.3s ease;
        }

        .ticket.used:hover .visited-stamp-overlay span {
            transform: rotate(-15deg) scale(1.05);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="bookings-container">
        <div class="booking-content">
            <div class="booking-header">
                <h2><i class="fas fa-ticket-alt"></i> My Safari Tickets</h2>
                <p>Your passport to amazing wildlife adventures</p>
            </div>
            <!-- Continue from where the document ended -->
            <div class="bookings-grid">
                <?php if (count($bookings) > 0): ?>
                    <?php foreach($bookings as $booking): ?>
                        <?php 
                            // Set booking status
                            $today = date('Y-m-d');
                            $visit_date = $booking['visit_date'];
                            
                            // Default to 'pending' if status is not set
                            $booking_status = isset($booking['status']) ? $booking['status'] : 'pending';
                            
                            if ($booking_status === 'cancelled') {
                                $status_class = 'status-cancelled';
                                $status_text = 'Cancelled';
                            } elseif ($visit_date < $today) {
                                $status_class = 'status-completed';
                                $status_text = 'Completed';
                            } elseif ($visit_date == $today) {
                                $status_class = 'status-today';
                                $status_text = 'Today';
                            } else {
                                $status_class = 'status-upcoming';
                                $status_text = 'Upcoming';
                            }
                            
                            // Get animal category for image
                            $animal_category = strtolower($booking['animal_experience'] ?? '');
                            $animal_images = [
                                'big cats' => 'tiger-experience.jpg',
                                'primates' => 'monkey-experience.jpg',
                                'elephants' => 'elephant-experience.jpg',
                                'reptiles' => 'reptile-experience.jpg',
                                'birds' => 'bird-experience.jpg',
                                'aquatic' => 'aquatic-experience.jpg'
                            ];
                            
                            // Default to first available category if not set
                            $image = isset($animal_images[$animal_category]) ? $animal_images[$animal_category] : array_values($animal_images)[0];
                        ?>
                        <div class="booking-card <?php echo $booking['status']; ?>">
                            <div class="ticket-stub"></div>
                            <div class="ticket-holes">
                                <div class="ticket-hole"></div>
                                <div class="ticket-hole"></div>
                                <div class="ticket-hole"></div>
                            </div>
                            
                            <?php if ($booking['status'] === 'used'): ?>
                                <div class="visited-overlay">
                                    <div class="visited-stamp">
                                        <div class="stamp-circle">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="stamp-text">
                                            <span>VISITED</span>
                                            <small><?php echo date('d M Y', strtotime($booking['used_at'])); ?></small>
                                            <small class="time"><?php echo date('h:i A', strtotime($booking['used_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-content">
                                <div class="qr-container">
                                    <?php if ($booking['status'] === 'pending' && strtotime($booking['visit_date']) === strtotime('today')): ?>
                                        <div id="qrcode_<?php echo $booking['id']; ?>" class="qr-code"></div>
                                        <button onclick="showQRModal(<?php echo $booking['id']; ?>, '<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>')" class="show-qr-btn">
                                            <i class="fas fa-qrcode"></i> Show QR Code
                                        </button>
                                    <?php elseif ($booking['status'] === 'used'): ?>
                                        <div class="qr-code used">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Visited on <?php echo date('d M Y', strtotime($booking['used_at'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="qr-code future">
                                            <i class="fas fa-ticket-alt"></i>
                                            <span class="countdown-label" data-date="<?php echo $booking['visit_date']; ?>">
                                                <?php 
                                                    $days_until = ceil((strtotime($booking['visit_date']) - time()) / 86400);
                                                    if ($days_until == 1) {
                                                        echo "Tomorrow";
                                                    } else {
                                                        echo $days_until . " days to go";
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="booking-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($booking['visit_date'])); ?>
                                    </div>
                                    
                                    <div class="booking-number">
                                        <i class="fas fa-hashtag"></i> TICKET #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    
                                    <div class="ticket-info">
                                        <div class="ticket-row total">
                                            <span>Total Tickets:</span>
                                            <span><?php 
                                                $total_tickets = $booking['adult_tickets'] + $booking['child_0_5_tickets'] + 
                                                                $booking['child_5_12_tickets'] + $booking['senior_tickets'];
                                                echo $total_tickets;
                                            ?></span>
                                        </div>
                                        <?php if ($booking['adult_tickets'] > 0): ?>
                                        <div class="ticket-row">
                                            <span>Adult:</span>
                                            <span><?php echo htmlspecialchars($booking['adult_tickets']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($booking['child_0_5_tickets'] > 0): ?>
                                        <div class="ticket-row">
                                            <span>Children (0-5):</span>
                                            <span><?php echo htmlspecialchars($booking['child_0_5_tickets']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($booking['child_5_12_tickets'] > 0): ?>
                                        <div class="ticket-row">
                                            <span>Children (5-12):</span>
                                            <span><?php echo htmlspecialchars($booking['child_5_12_tickets']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($booking['senior_tickets'] > 0): ?>
                                        <div class="ticket-row">
                                            <span>Senior:</span>
                                            <span><?php echo htmlspecialchars($booking['senior_tickets']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="total-amount">
                                            <span>Total:</span>
                                            <span>₹<?php echo number_format($booking['total_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add individual tickets section -->
                            <div class="individual-tickets">
                                <?php 
                                // Generate individual tickets based on ticket types
                                $ticket_types = [
                                    'adult' => $booking['adult_tickets'],
                                    'child_0_5' => $booking['child_0_5_tickets'],
                                    'child_5_12' => $booking['child_5_12_tickets'],
                                    'senior' => $booking['senior_tickets']
                                ];
                                
                                $ticket_names = [
                                    'adult' => 'Adult',
                                    'child_0_5' => 'Child (0-5)',
                                    'child_5_12' => 'Child (5-12)',
                                    'senior' => 'Senior'
                                ];
                                
                                $ticket_prices = [
                                    'adult' => 200, // You should replace these with actual prices from your database
                                    'child_0_5' => 0,
                                    'child_5_12' => 100,
                                    'senior' => 150
                                ];
                                
                                foreach ($ticket_types as $type => $quantity):
                                    if ($quantity > 0):
                                ?>
                                    <div class="ticket ticket-type-<?php echo $type; ?>">
                                        <?php if ($booking['status'] === 'used'): ?>
                                            <div class="visited-stamp-overlay">
                                                <span>VISITED</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="ticket-header">
                                            <?php echo $ticket_names[$type]; ?> Tickets (<?php echo $quantity; ?>)
                                        </div>
                                        <div class="ticket-status ticket-status-<?php echo strtolower($booking['status']); ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </div>
                                        <div class="ticket-body">
                                            <div class="ticket-qr">
                                                <?php if ($booking['status'] === 'pending' && strtotime($booking['visit_date']) === strtotime('today')): ?>
                                                    <div id="type_qr_<?php echo $booking['id']; ?>_<?php echo $type; ?>" class="type-qr"></div>
                                                <?php elseif ($booking['status'] === 'used'): ?>
                                                    <div class="used-qr">
                                                        <i class="fas fa-check-circle"></i>
                                                        <span>Used</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="pending-qr">
                                                        <i class="fas fa-qrcode fa-3x"></i>
                                                        <span>Available on visit date</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ticket-info">
                                                <p><strong>Booking #:</strong> <?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                                <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($booking['visit_date'])); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo $quantity; ?></p>
                                                <p class="ticket-price">₹<?php echo number_format($ticket_prices[$type] * $quantity, 2); ?></p>
                                            </div>
                                        </div>
                                        <div class="ticket-footer">
                                            <div class="ticket-actions">
                                                <a href="#" class="ticket-btn ticket-btn-view" onclick="showTypeTicket(<?php echo $booking['id']; ?>, '<?php echo $type; ?>', <?php echo $quantity; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                <a href="#" class="ticket-btn ticket-btn-download" onclick="downloadTypeTicket(<?php echo $booking['id']; ?>, '<?php echo $type; ?>', <?php echo $quantity; ?>)">
                                                    <i class="fas fa-download"></i> Save
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            
                            <?php if ($booking['status'] === 'pending'): ?>
                            <div class="booking-actions">
                                <a href="#" class="action-btn cancel-btn" onclick="showCancelModal(<?php echo $booking['id']; ?>); return false;">
                                    <i class="fas fa-times-circle"></i> Cancel Booking
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-bookings">
                        <div class="no-bookings-content">
                            <i class="fas fa-paw fa-3x"></i>
                            <h3>No Bookings Found</h3>
                            <p>You haven't made any safari bookings yet. Start your wildlife adventure today!</p>
                            <a href="booking.php" class="book-now-btn">
                                <i class="fas fa-ticket-alt"></i>
                                Book Your Adventure
                            </a>
                        </div>
                    </div>

                    <style>
                        .no-bookings {
                            grid-column: 1 / -1;
                            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
                            border-radius: 20px;
                            padding: 60px 20px;
                            text-align: center;
                            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                            backdrop-filter: blur(10px);
                            border: 2px dashed var(--main);
                            animation: fadeIn 0.6s ease-out;
                        }

                        .no-bookings-content {
                            max-width: 400px;
                            margin: 0 auto;
                        }

                        .no-bookings i {
                            color: var(--main);
                            margin-bottom: 20px;
                            opacity: 0.8;
                            animation: bounce 2s infinite;
                        }

                        .no-bookings h3 {
                            font-size: 2.4rem;
                            color: var(--main);
                            margin-bottom: 15px;
                            font-weight: 600;
                        }

                        .no-bookings p {
                            font-size: 1.6rem;
                            color: #666;
                            margin-bottom: 30px;
                            line-height: 1.6;
                        }

                        .book-now-btn {
                            display: inline-flex;
                            align-items: center;
                            gap: 10px;
                            background: var(--main);
                            color: white;
                            padding: 15px 30px;
                            border-radius: 50px;
                            text-decoration: none;
                            font-size: 1.6rem;
                            font-weight: 500;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                        }

                        .book-now-btn:hover {
                            transform: translateY(-3px);
                            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                            background: linear-gradient(45deg, var(--main), #2c8a3d);
                        }

                        @keyframes bounce {
                            0%, 100% {
                                transform: translateY(0);
                            }
                            50% {
                                transform: translateY(-10px);
                            }
                        }

                        @keyframes fadeIn {
                            from {
                                opacity: 0;
                                transform: translateY(20px);
                            }
                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }

                        @media (max-width: 768px) {
                            .no-bookings {
                                padding: 40px 15px;
                            }

                            .no-bookings h3 {
                                font-size: 2rem;
                            }

                            .no-bookings p {
                                font-size: 1.4rem;
                            }

                            .book-now-btn {
                                padding: 12px 25px;
                                font-size: 1.4rem;
                            }
                        }
                    </style>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add a subtle animation to the ticket cards
        const cards = document.querySelectorAll('.booking-card');
        
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'fadeInUp 0.6s ease forwards';
                card.style.opacity = '1';
            }, index * 150);
        });
        
        // Add hover effects to make the cards more interactive
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) rotate(1deg)';
                this.style.boxShadow = '0 15px 35px rgba(0,0,0,0.2)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) rotate(0)';
                this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
            });
        });
        
        // Initialize individual ticket QR codes
        const tickets = document.querySelectorAll('.individual-qr');
        tickets.forEach(ticket => {
            const id = ticket.id.split('_');
            const bookingId = id[2];
            const ticketNum = id[3];
            
            new QRCode(ticket, {
                text: JSON.stringify({
                    booking_id: bookingId,
                    ticket_num: ticketNum,
                    timestamp: Date.now()
                }),
                width: 100,
                height: 100,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    });

    // Function to show individual ticket modal
    window.showIndividualTicket = function(bookingId, ticketNum, ticketType) {
        const modal = document.getElementById('qrModal');
        const container = document.getElementById('qrcode-container');
        const modalBookingId = document.getElementById('modalBookingId');
        
        // Clear previous content
        container.innerHTML = '';
        
        // Set booking ID text
        modalBookingId.textContent = `Booking #${String(bookingId).padStart(6, '0')}-${ticketNum} (${ticketType.replace('_', ' ').toUpperCase()})`;
        
        // Create QR code
        new QRCode(container, {
            text: JSON.stringify({
                booking_id: bookingId,
                ticket_num: ticketNum,
                ticket_type: ticketType,
                timestamp: Date.now()
            }),
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Show modal
        modal.style.display = 'block';
    };

    window.showTypeTicket = function(bookingId, ticketType, quantity) {
        console.log('Showing ticket type:', ticketType, 'for booking:', bookingId);
        
        const modal = document.getElementById('qrModal');
        const container = document.getElementById('qrcode-container');
        const modalBookingId = document.getElementById('modalBookingId');
        
        // Clear previous content
        container.innerHTML = '';
        
        // Set booking ID text
        modalBookingId.textContent = `Booking #${String(bookingId).padStart(6, '0')} - ${ticketType.replace('_', ' ').toUpperCase()} (${quantity})`;
        
        // Generate QR code
        new QRCode(container, {
            text: JSON.stringify({
                booking_id: bookingId,
                ticket_type: ticketType,
                quantity: quantity,
                timestamp: Date.now()
            }),
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Show modal
        modal.style.display = 'block';
    };

    window.downloadTypeTicket = function(bookingId, ticketType, quantity) {
        // Implementation for downloading ticket
        alert('Download functionality will be implemented here');
    };

    // Initialize QR codes for today's tickets
    document.addEventListener('DOMContentLoaded', function() {
        // Generate QR codes for today's pending tickets
        const today = new Date().toISOString().split('T')[0];
        
        document.querySelectorAll('[id^="type_qr_"]').forEach(element => {
            const parts = element.id.split('_');
            const bookingId = parts[2];
            const ticketType = parts[3];
            
            // Generate QR code
            new QRCode(element, {
                text: JSON.stringify({
                    booking_id: bookingId,
                    ticket_type: ticketType,
                    timestamp: Date.now()
                }),
                width: 100,
                height: 100,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    });
    </script>

    <style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .booking-card {
        opacity: 0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    /* Add safari-themed textures and patterns */
    body {
        background-image: url('images/subtle-safari-bg.png');
        background-attachment: fixed;
    }
    
    /* Add a themed tear effect to the ticket */
    .booking-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url('images/ticket-texture.png');
        opacity: 0.05;
        pointer-events: none;
    }
    
    /* Add a pulsing effect to the Today tickets */
    .status-today {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 143, 0, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(255, 143, 0, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(255, 143, 0, 0);
        }
    }
    
    /* Enhance the animal category tags */
    .animal-category {
        background: linear-gradient(135deg, rgba(255, 107, 34, 0.9) 0%, rgba(255, 85, 0, 0.9) 100%);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        text-transform: uppercase;
        font-size: 1.2rem;
        letter-spacing: 1px;
    }
    
    /* Add a safari-themed footer */
    .safari-footer {
        background: linear-gradient(to right, #8B4513, #A0522D);
        color: white;
        text-align: center;
        padding: 20px 0;
        margin-top: 50px;
    }
    </style>

    <div class="safari-footer">
        <p>© <?php echo date('Y'); ?> SafariGate Zoo - Your Gateway to Wild Adventures</p>
    </div>

    <!-- Add this modal structure right before the closing </body> tag -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="qr-modal-content">
                <h3>Ticket QR Code</h3>
                <div class="qr-display">
                    <div id="qrcode-container"></div>
                    <p id="modalBookingId"></p>
                </div>
                <div class="qr-instructions">
                    <p><i class="fas fa-info-circle"></i> Present this QR code at the entrance for quick access</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Make sure the QR code library is loaded before your script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing QR code functionality');
            
            const modal = document.getElementById('qrModal');
            const closeBtn = document.getElementsByClassName('close-modal')[0];
            let currentQR = null;

            window.showQRModal = function(bookingId, formattedId) {
                console.log('Showing QR modal for booking:', bookingId, formattedId);
                
                // Clear previous QR code
                const container = document.getElementById('qrcode-container');
                container.innerHTML = '';
                
                // Create simple QR code with just the booking ID
                // This is more reliable and doesn't require complex user validation
                const qrData = JSON.stringify({
                    booking_id: bookingId
                });
                
                console.log('Generated QR data:', qrData);

                try {
                    // Generate new QR code
                    new QRCode(container, {
                        text: qrData,
                        width: 256,
                        height: 256,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    
                    console.log('QR code generated successfully');

                    // Update booking ID display
                    document.getElementById('modalBookingId').textContent = 'Booking #' + formattedId;
                    
                    // Show modal
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } catch (error) {
                    console.error('Error generating QR code:', error);
                    container.innerHTML = 'Error generating QR code. Please try again.';
                }
            };

            // Close modal when clicking the × button
            closeBtn.onclick = function() {
                console.log('Closing modal via close button');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            };

            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == modal) {
                    console.log('Closing modal via outside click');
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            };

            // Close modal on escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.style.display === 'block') {
                    console.log('Closing modal via escape key');
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        #qrcode-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            min-height: 256px;
        }

        #qrcode-container img {
            max-width: 100%;
            height: auto;
        }

        .qr-display {
            background: #f8f8f8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }

        .qr-modal-content h3 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--main);
            font-size: 2rem;
        }

        .qr-instructions {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #e8f5e9;
            border-radius: 8px;
        }

        .qr-instructions p {
            color: #2e7d32;
            font-size: 1.4rem;
        }

        #modalBookingId {
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--main);
            margin: 10px 0;
        }
    </style>

    <!-- Add this at the bottom of the file, before closing </body> tag -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Cancel Booking</h3>
                <span class="close-modal" onclick="closeCancelModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="warning-message">
                    <p><strong>Please Note:</strong></p>
                    <ul>
                        <li>This action cannot be undone</li>
                        <li>No refund will be processed for cancelled bookings</li>
                        <li>The tickets will be made available for other visitors</li>
                    </ul>
                </div>
                <p>Are you sure you want to cancel this booking?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCancelModal()">No, Keep Booking</button>
                <a href="#" id="confirmCancel" class="btn btn-danger">Yes, Cancel Booking</a>
            </div>
        </div>
    </div>

    <style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    .modal-header {
        background: #f8d7da;
        color: #721c24;
        padding: 15px 20px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-body {
        padding: 20px;
    }

    .warning-message {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        margin-bottom: 20px;
    }

    .warning-message ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    .modal-footer {
        padding: 15px 20px;
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        border-radius: 0 0 8px 8px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
        text-decoration: none;
    }

    .close-modal {
        cursor: pointer;
        font-size: 24px;
    }
    </style>

    <script>
    // Make sure this script is placed after the modal HTML in your document
    document.addEventListener('DOMContentLoaded', function() {
        // Define the cancel modal functions in the global scope
        window.showCancelModal = function(bookingId) {
            console.log('Opening cancel modal for booking ID:', bookingId);
            const modal = document.getElementById('cancelModal');
            const confirmLink = document.getElementById('confirmCancel');
            
            if (!modal || !confirmLink) {
                console.error('Modal elements not found!');
                return;
            }
            
            confirmLink.href = `cancel-booking.php?id=${bookingId}`;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        };

        window.closeCancelModal = function() {
            console.log('Closing cancel modal');
            const modal = document.getElementById('cancelModal');
            
            if (!modal) {
                console.error('Modal element not found!');
                return;
            }
            
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        };

        // Add event listener to close button
        const closeModalBtn = document.querySelector('#cancelModal .close-modal');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                closeCancelModal();
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                closeCancelModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('cancelModal');
                if (modal && modal.style.display === 'block') {
                    closeCancelModal();
                }
            }
        });
        
        // Add event listener to the "No, Keep Booking" button
        const keepBookingBtn = document.querySelector('#cancelModal .btn-secondary');
        if (keepBookingBtn) {
            keepBookingBtn.addEventListener('click', function() {
                closeCancelModal();
            });
        }
        
        console.log('Cancel modal event listeners initialized');
    });
    </script>

    <!-- Add this JavaScript to update the countdown in real-time -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update countdown timers
            function updateCountdowns() {
                document.querySelectorAll('.countdown-label').forEach(label => {
                    const targetDate = new Date(label.dataset.date);
                    const now = new Date();
                    
                    // Calculate days difference
                    const diffTime = targetDate - now;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays === 0) {
                        label.innerHTML = "Today!";
                        label.style.background = "#ffc107";
                        label.style.color = "#212529";
                    } else if (diffDays === 1) {
                        label.innerHTML = "Tomorrow";
                    } else {
                        label.innerHTML = diffDays + " days to go";
                    }
                });
            }
            
            // Initial update
            updateCountdowns();
            
            // Update every minute
            setInterval(updateCountdowns, 60000);
        });
    </script>

    <!-- Add toggle functionality for individual tickets -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add toggle buttons to each booking card
        const bookingCards = document.querySelectorAll('.booking-card');
        
        bookingCards.forEach(card => {
            const ticketsSection = card.querySelector('.individual-tickets');
            if (!ticketsSection) return;
            
            // Hide tickets section initially
            ticketsSection.style.display = 'none';
            
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'individual-tickets-toggle';
            toggleBtn.innerHTML = '<i class="fas fa-ticket-alt"></i> Show Individual Tickets <i class="fas fa-chevron-down"></i>';
            
            // Insert button before tickets section
            card.insertBefore(toggleBtn, ticketsSection);
            
            // Add toggle functionality
            toggleBtn.addEventListener('click', function() {
                const isVisible = ticketsSection.style.display === 'grid';
                ticketsSection.style.display = isVisible ? 'none' : 'grid';
                
                // Toggle active class for button styling
                this.classList.toggle('active');
                
                this.innerHTML = isVisible ? 
                    '<i class="fas fa-ticket-alt"></i> Show Individual Tickets <i class="fas fa-chevron-down"></i>' : 
                    '<i class="fas fa-ticket-alt"></i> Hide Individual Tickets <i class="fas fa-chevron-up"></i>';
            });
        });
        
        // Add subtle animation to card appearance
        setTimeout(() => {
            bookingCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }, 300);
    });
    </script>
</body>
</html>