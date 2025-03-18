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
        .bookings-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
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
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            padding: 30px 0;
        }
        
        .booking-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: var(--main);
            border-radius: 2px;
        }

        .booking-header h2 {
            font-size: 3.5rem;
            color: var(--main);
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .booking-header p {
            font-size: 1.6rem;
            color: #666;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            padding: 20px;
        }

        .booking-card {
            background: var(--white);
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .ticket-stub {
            background: var(--main);
            width: 20px;
            height: 100%;
            position: absolute;
            left: 0;
        }

        .ticket-holes {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 30px;
            z-index: 1;
        }

        .ticket-hole {
            width: 20px;
            height: 20px;
            background: var(--bg);
            border-radius: 50%;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .booking-content {
            margin-left: 20px;
            padding: 20px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
        }

        .qr-container {
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            border-right: 1px dashed #ddd;
        }

        .qr-code {
            width: 150px;
            height: 150px;
            padding: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .qr-info {
            text-align: center;
        }

        .qr-info span {
            display: block;
            font-size: 1.4rem;
            font-weight: 500;
            color: var(--main);
        }

        .qr-info small {
            font-size: 1.2rem;
            color: #666;
        }

        .booking-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.4rem;
            font-weight: 500;
        }

        .booking-details {
            padding: 0 20px;
        }

        .booking-date {
            font-size: 2rem;
            color: var(--main);
            margin-bottom: 15px;
        }

        .booking-number {
            font-size: 1.4rem;
            color: #666;
            margin-bottom: 20px;
        }

        .ticket-info {
            background: var(--bg);
            padding: 20px;
            border-radius: 10px;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }

        .total-amount {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            font-size: 1.8rem;
            font-weight: 500;
            color: var(--main);
        }

        .booking-actions {
            padding: 20px;
            padding-left: 40px;
            display: flex;
            gap: 15px;
            border-top: 1px solid #eee;
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-btn {
            background: var(--main);
            color: white;
        }

        .cancel-btn {
            background: white;
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        @media (max-width: 768px) {
            .booking-content {
                grid-template-columns: 1fr;
            }

            .qr-container {
                border-right: none;
                border-bottom: 1px dashed #ddd;
                padding-bottom: 20px;
            }

            .booking-details {
                padding: 0;
            }

            .booking-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .booking-card.used {
            position: relative;
            overflow: hidden;
        }
        
        .booking-card.used::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                rgba(40, 167, 69, 0.05),
                rgba(40, 167, 69, 0.05) 10px,
                rgba(40, 167, 69, 0.1) 10px,
                rgba(40, 167, 69, 0.1) 20px
            );
            z-index: 1;
        }
        
        .visited-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(40, 167, 69, 0.1);
            backdrop-filter: blur(2px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
            animation: fadeIn 0.5s ease-out;
        }
        
        .visited-stamp {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: rotate(-15deg);
            border: 2px solid #28a745;
            animation: stampIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .stamp-circle {
            width: 50px;
            height: 50px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .stamp-circle i {
            color: white;
            font-size: 24px;
            animation: checkmark 0.5s ease-out 0.2s both;
        }
        
        .stamp-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .stamp-text span {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            letter-spacing: 2px;
        }
        
        .stamp-text small {
            color: #666;
            font-size: 14px;
        }
        
        .stamp-text .time {
            font-family: monospace;
        }
        
        @keyframes stampIn {
            from {
                transform: rotate(-15deg) scale(0);
                opacity: 0;
            }
            to {
                transform: rotate(-15deg) scale(1);
                opacity: 1;
            }
        }
        
        @keyframes checkmark {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .qr-code.used {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .qr-code.pending {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .show-qr-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
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
                                            <span>Visited on <?php echo date('d M Y H:i', strtotime($booking['used_at'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="qr-code pending">
                                            <i class="fas fa-clock"></i>
                                            <span>Available on visit date</span>
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
                                        <div class="ticket-row">
                                            <span>Total Tickets:</span>
                                            <span><?php 
                                                $total_tickets = $booking['adult_tickets'] + 
                                                               $booking['child_0_5_tickets'] + 
                                                               $booking['child_5_12_tickets'] + 
                                                               $booking['senior_tickets'];
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
                            
                            <div class="booking-actions">
                                <a href="#" class="action-btn view-btn" onclick="showQRModal('<?php echo $booking['id']; ?>', '<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>')">
                                    <i class="fas fa-eye"></i> show QR
                                </a>
                                
                                <?php if ($status_text == 'Upcoming' || $status_text == 'Today'): ?>
                                <button class="action-btn cancel-btn" onclick="showCancelModal(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
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
                <h3>Booking QR Code</h3>
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
                
                // Create QR code data - match the format expected by scan_ticket.php
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
                <span class="close-modal">&times;</span>
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
    function showCancelModal(bookingId) {
        const modal = document.getElementById('cancelModal');
        const confirmLink = document.getElementById('confirmCancel');
        confirmLink.href = `cancel-booking.php?id=${bookingId}`;
        modal.style.display = 'block';
    }

    function closeCancelModal() {
        const modal = document.getElementById('cancelModal');
        modal.style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('cancelModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeCancelModal();
        }
    });
    </script>
</body>
</html>