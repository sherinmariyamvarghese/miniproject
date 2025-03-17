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
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .booking-card:hover {
            transform: translateY(-10px) rotate(1deg);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        /* Ticket design elements */
        .booking-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 15px;
            right: 15px;
            height: 20px;
            border-radius: 0 0 15px 15px;
            background-color: var(--main);
        }
        
        .ticket-stub {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 30px;
            background: repeating-linear-gradient(
                45deg,
                var(--main),
                var(--main) 10px,
                #ff7b3a 10px,
                #ff7b3a 20px
            );
            z-index: 1;
        }
        
        .ticket-stub::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 15px;
            height: 30px;
            background-color: var(--white);
            border-radius: 15px 0 0 15px;
            transform: translateY(-50%);
        }
        
        .ticket-holes {
            position: absolute;
            top: 0;
            right: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            z-index: 1;
        }
        
        .ticket-hole {
            width: 20px;
            height: 20px;
            background-color: var(--white);
            border-radius: 50%;
            border: 1px dashed #ccc;
        }

        .booking-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .booking-image::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
        }

        .booking-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        
        .booking-card:hover .booking-image img {
            transform: scale(1.1);
        }
        
        .animal-info {
            position: absolute;
            bottom: 10px;
            left: 15px;
            color: white;
            font-size: 1.4rem;
            font-weight: bold;
            z-index: 1;
        }

        .booking-status {
            position: absolute;
            top: 25px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.4rem;
            font-weight: bold;
            z-index: 1;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .status-upcoming {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .status-today {
            background: #fff8e1;
            color: #ff8f00;
            border: 1px solid #ffe082;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .booking-details {
            padding: 25px 20px 20px 40px;
        }

        .booking-date {
            font-size: 2.2rem;
            color: var(--main);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 15px;
        }

        .booking-date i {
            margin-right: 10px;
            color: #ff6b22;
        }
        
        .booking-number {
            font-size: 1.4rem;
            color: #666;
            margin-bottom: 15px;
            font-family: "Courier New", monospace;
            letter-spacing: 1px;
        }

        .ticket-info {
            margin: 15px 0;
            padding: 15px;
            background: var(--bg);
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('images/paw-pattern.png');
            opacity: 0.05;
            pointer-events: none;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: var(--black);
        }
        
        .ticket-row span:first-child {
            font-weight: 500;
        }

        .total-amount {
            font-size: 2rem;
            color: var(--main);
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            display: flex;
            justify-content: space-between;
        }

        .booking-actions {
            padding: 15px 20px 15px 40px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn i {
            margin-right: 8px;
        }

        .view-btn {
            background: var(--main);
            color: var(--white);
            border: none;
        }

        .view-btn:hover {
            background: #ff5500;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 34, 0.3);
        }

        .cancel-btn {
            background: #fff;
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .cancel-btn:hover {
            background: #dc3545;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .no-bookings {
            text-align: center;
            padding: 60px 40px;
            background: var(--white);
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .no-bookings::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('images/paw-pattern.png');
            opacity: 0.05;
            pointer-events: none;
        }

        .no-bookings i {
            font-size: 6rem;
            color: var(--main);
            margin-bottom: 25px;
            display: block;
        }

        .no-bookings h3 {
            font-size: 2.8rem;
            color: var(--black);
            margin-bottom: 15px;
        }

        .no-bookings p {
            font-size: 1.8rem;
            color: #666;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .book-now-btn {
            display: inline-block;
            padding: 15px 40px;
            background: var(--main);
            color: var(--white);
            border-radius: 50px;
            font-size: 1.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 107, 34, 0.3);
        }

        .book-now-btn:hover {
            background: #ff5500;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 34, 0.4);
        }
        
        .qr-code {
            position: absolute;
            right: 20px;
            top: 25px;
            width: 60px;
            height: 60px;
            background: url('images/qr-placeholder.png');
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.8;
        }
        
        .ticket-stamp {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: rotate(-15deg);
            opacity: 0.6;
            font-family: "Arial", sans-serif;
        }
        
        .ticket-stamp-inner {
            width: 100%;
            height: 100%;
            border: 2px dashed #999;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c62828;
            font-weight: bold;
            font-size: 1.2rem;
            text-transform: uppercase;
            padding: 10px;
            text-align: center;
        }
        
        .animal-category {
            position: absolute;
            top: 25px;
            left: 40px;
            padding: 5px 15px;
            background: rgba(255, 107, 34, 0.9);
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 4px;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .bookings-grid {
                grid-template-columns: 1fr;
            }
            
            .ticket-stub {
                width: 20px;
            }
            
            .booking-details {
                padding-left: 30px;
            }
            
            .booking-actions {
                padding-left: 30px;
                flex-direction: column;
                gap: 10px;
            }
            
            .action-btn {
                width: 100%;
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
                            $animal_category = strtolower($booking['animal_experience'] ?? 'general');
                            $animal_images = [
                                'big cats' => 'tiger-experience.jpg',
                                'primates' => 'monkey-experience.jpg',
                                'elephants' => 'elephant-experience.jpg',
                                'reptiles' => 'reptile-experience.jpg',
                                'birds' => 'bird-experience.jpg',
                                'aquatic' => 'aquatic-experience.jpg',
                                'general' => 'safari-experience.jpg'
                            ];
                            
                            $image = isset($animal_images[$animal_category]) ? $animal_images[$animal_category] : 'safari-experience.jpg';
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
                            
                            <div class="booking-image">
                                <img src="images/<?php echo $image; ?>" alt="<?php echo htmlspecialchars($booking['animal_experience'] ?? 'General'); ?> Experience">
                                <span class="animal-category"><?php echo htmlspecialchars($booking['animal_experience'] ?? 'General'); ?></span>
                                <div class="animal-info">
                                    <i class="fas fa-paw"></i> <?php echo htmlspecialchars($booking['animal_experience'] ?? 'General'); ?> Experience
                                </div>
                            </div>
                            
                            <div class="qr-code" title="Scan this at the entrance"></div>
                            
                            <div class="booking-details">
                                <div class="booking-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($booking['visit_date'])); ?>
                                </div>
                                
                                <div class="booking-number">
                                    <i class="fas fa-hashtag"></i> TICKET #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>
                                </div>
                                
                                <div class="ticket-info">
                                    <div class="ticket-row">
                                        <span>Tickets:</span>
                                        <span><?php echo htmlspecialchars($booking['ticket_details']); ?></span>
                                    </div>
                                    <div class="ticket-row">
                                        <span>Adult Tickets:</span>
                                        <span><?php echo htmlspecialchars($booking['adult_tickets']); ?></span>
                                    </div>
                                    <div class="ticket-row">
                                        <span>Children (0-5):</span>
                                        <span><?php echo htmlspecialchars($booking['child_0_5_tickets']); ?></span>
                                    </div>
                                    <div class="ticket-row">
                                        <span>Children (5-12):</span>
                                        <span><?php echo htmlspecialchars($booking['child_5_12_tickets']); ?></span>
                                    </div>
                                    <div class="ticket-row">
                                        <span>Senior Tickets:</span>
                                        <span><?php echo htmlspecialchars($booking['senior_tickets']); ?></span>
                                    </div>
                                    
                                    <div class="total-amount">
                                        <span>Total:</span>
                                        <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($status_text == 'Completed'): ?>
                                <div class="ticket-stamp">
                                    <div class="ticket-stamp-inner">
                                        Visited
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-actions">
                                <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="action-btn view-btn">
                                    <i class="fas fa-eye"></i> View Details
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