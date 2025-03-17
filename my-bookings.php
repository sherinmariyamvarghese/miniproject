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
                        <div class="booking-card">
                            <div class="ticket-stub"></div>
                            <div class="ticket-holes">
                                <div class="ticket-hole"></div>
                                <div class="ticket-hole"></div>
                                <div class="ticket-hole"></div>
                            </div>
                            
                            <div class="booking-status <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </div>
                            
                            <div class="booking-content">
                                <div class="qr-container">
                                    <?php
                                    $qr_file = 'uploads/qrcodes/booking_' . $booking['id'] . '.png';
                                    ?>
                                    <img src="<?php echo $qr_file; ?>" alt="Booking QR Code" class="qr-code">
                                    <div class="qr-info">
                                        <span>Scan for Entry</span>
                                        <small>Booking #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></small>
                                    </div>
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
                                <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="action-btn view-btn">
                                    <i class="fas fa-eye"></i> show QR
                                </a>
                                
                                <?php if ($status_text == 'Upcoming' || $status_text == 'Today'): ?>
                                <a href="cancel-booking.php?id=<?php echo $booking['id']; ?>" class="action-btn cancel-btn" onclick="return confirm('Are you sure you want to cancel this booking?');">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-bookings">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Bookings Found</h3>
                        <p>You haven't made any safari bookings yet. Start your wildlife adventure today!</p>
                        <a href="book-now.php" class="book-now-btn">Book Your Adventure</a>
                    </div>
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
</body>
</html>