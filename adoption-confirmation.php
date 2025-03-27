<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';

// Import namespaces at file level
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Check if PHPMailer files exist before requiring them
$phpmailer_dir = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
$phpmailer_available = file_exists($phpmailer_dir . 'PHPMailer.php');

if ($phpmailer_available) {
    require_once $phpmailer_dir . 'PHPMailer.php';
    require_once $phpmailer_dir . 'SMTP.php';
    require_once $phpmailer_dir . 'Exception.php';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get adoptions data
$adoptions = isset($_POST['adoptions']) ? json_decode($_POST['adoptions'], true) : null;

if (!$adoptions) {
    header('Location: adoption.php');
    exit;
}

// Calculate total amount
$totalAmount = 0;
foreach ($adoptions as $adoption) {
    $totalAmount += $adoption['rate'];
}

function generateAdoptionCertificate($adoptions) {
    $dir = __DIR__ . '/uploads/adoption_certificates';
    
    if (!file_exists($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: " . $dir);
            throw new Exception("Failed to create certificate directory");
        }
    }
    
    try {
        // Create new PDF document in landscape orientation
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('SafariGate Zoo');
        $pdf->SetAuthor('SafariGate Zoo');
        $pdf->SetTitle('Multiple Animals Adoption Certificate');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add a single page in landscape orientation
        $pdf->AddPage('L', 'A4');
        
        // Add decorative border
        $pdf->SetLineStyle(array('width' => 2, 'color' => array(218, 165, 32))); // Golden color
        $pdf->RoundedRect(10, 10, 277, 190, 3.50, '1111');
        
        // Add inner decorative border
        $pdf->SetLineStyle(array('width' => 1, 'color' => array(218, 165, 32)));
        $pdf->RoundedRect(15, 15, 267, 180, 3.50, '1111');
        
        $html = '
            <style>
                .certificate-container { 
                    text-align: center; 
                    color: #2c3e50; 
                    padding: 20px;
                    background: url("images/certificate-bg.png") no-repeat center center;
                    background-size: cover;
                }
                .certificate-header { 
                    font-size: 40pt; 
                    color: #c0392b; 
                    margin: 20px 0 10px; 
                    font-family: times; 
                    text-transform: uppercase; 
                    letter-spacing: 3px;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                }
                .certificate-subheader { 
                    font-size: 18pt; 
                    color: #34495e; 
                    margin-bottom: 20px; 
                    font-style: italic;
                }
                .certificate-seal {
                    font-size: 14pt;
                    color: #7f8c8d;
                    margin: 10px 0;
                }
                .adopter-name { 
                    font-size: 30pt; 
                    color: #2c3e50; 
                    font-family: times; 
                    margin: 25px 0;
                    font-style: italic;
                }
                .adoption-text {
                    font-size: 14pt;
                    color: #34495e;
                    margin: 15px 0;
                    line-height: 1.6;
                }
                .animals-list {
                    width: 80%;
                    margin: 20px auto;
                    font-size: 14pt;
                    color: #2c3e50;
                    text-align: center;
                }
                .animals-list td {
                    padding: 5px 15px;
                    border-bottom: 1px solid #bdc3c7;
                }
                .certificate-footer { 
                    margin-top: 30px;
                    color: #34495e;
                }
                .signature-line {
                    border-top: 1px solid #2c3e50;
                    width: 200px;
                    margin: 10px auto;
                }
                .signature-title {
                    font-size: 12pt;
                    color: #7f8c8d;
                }
                .date-issued {
                    font-style: italic;
                    color: #7f8c8d;
                    font-size: 12pt;
                    margin-top: 20px;
                }
                .certificate-border {
                    border: 2px solid #daa520;
                    padding: 20px;
                    margin: 10px;
                }
                .corner-decoration {
                    font-size: 24pt;
                    color: #daa520;
                }
            </style>
            
            <div class="certificate-container">
                <div class="corner-decoration">❧</div>
                <div class="certificate-header">Certificate of Adoption</div>
                <div class="certificate-subheader">SafariGate Zoo Animal Adoption Program</div>
                <div class="certificate-seal">Official Certificate of Animal Adoption</div>
                
                <div class="adoption-text">This is to certify that</div>
                <div class="adopter-name">' . htmlspecialchars($adoptions[0]['adopter_name']) . '</div>
                <div class="adoption-text">has generously adopted the following animals:</div>
                
                <table class="animals-list" align="center">
                    <tr>
                        <td><strong>Animal Name</strong></td>
                        <td><strong>Adoption Period</strong></td>
                    </tr>';
        
        foreach ($adoptions as $adoption) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($adoption['animal_name']) . '</td>
                    <td>' . $adoption['period_type'] . '</td>
                </tr>';
        }
        
        $html .= '</table>
                
                <div class="adoption-text">
                    Through this adoption, the certificate holder contributes to the care,<br>
                    conservation, and welfare of these magnificent creatures.
                </div>
                
                <div class="certificate-footer">
                    <table width="100%">
                        <tr>
                            <td width="50%" align="center">
                                <div class="signature-line"></div>
                                <div class="signature-title">Zoo Director</div>
                            </td>
                            <td width="50%" align="center">
                                <div class="signature-line"></div>
                                <div class="signature-title">Program Coordinator</div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="date-issued">
                    Issued on ' . date('F j, Y') . '<br>
                    Certificate ID: ' . implode(', ', array_column($adoptions, 'adoption_id')) . '
                </div>
                
                <div class="corner-decoration" style="transform: rotate(180deg);">❧</div>
            </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save PDF
        $filename = 'adoption_group_' . time() . '.pdf';
        $pdf_path = $dir . '/' . $filename;
        $pdf->Output($pdf_path, 'F');
        
        return $pdf_path;
    } catch (Exception $e) {
        error_log("Error generating adoption certificate: " . $e->getMessage());
        throw $e;
    }
}

function generateAdoptionBill($adoptions) {
    $dir = __DIR__ . '/uploads/adoption_bills';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('SafariGate Zoo');
        $pdf->SetAuthor('SafariGate Zoo');
        $pdf->SetTitle('Group Adoption Bill');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // Add zoo logo if exists
        if (file_exists(__DIR__ . '/images/zoo-logo.png')) {
            $pdf->Image(__DIR__ . '/images/zoo-logo.png', 15, 10, 40);
        }
        
        // Calculate total amount
        $total_amount = array_sum(array_column($adoptions, 'amount'));
        
        $html = '
            <style>
                .bill-container { color: #333; line-height: 1.6; margin-top: 60px; }
                .bill-header { border-bottom: 2px solid #ff6e01; padding: 10px; margin-bottom: 20px; }
                .company-info { color: #666; font-size: 10pt; }
                .bill-title { font-size: 24pt; color: #ff6e01; margin: 20px 0; }
                .section { margin: 15px 0; padding: 10px; background-color: #f8f8f8; border-radius: 5px; }
                .section-title { color: #ff6e01; font-size: 14pt; margin-bottom: 10px; font-weight: bold; }
                .animal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 10px 0; }
                .animal-item { display: flex; align-items: center; background: white; padding: 10px; border-radius: 5px; }
                .animal-image { width: 60px; height: 60px; border-radius: 30px; margin-right: 10px; }
                .animal-details { flex-grow: 1; }
                .amount-table { width: 100%; margin-top: 20px; border-collapse: collapse; }
                .amount-table th { background-color: #ff6e01; color: white; padding: 8px; }
                .amount-table td { padding: 8px; border-bottom: 1px solid #ddd; }
                .total-row { background-color: #f8f8f8; font-weight: bold; }
            </style>
            
            <div class="bill-container">
                <div class="bill-header">
                    <table width="100%">
                        <tr>
                            <td width="60%">
                                <h1>SafariGate Zoo</h1>
                                <div class="company-info">
                                    123 Zoo Avenue<br>
                                    City, State 12345<br>
                                    Phone: (123) 456-7890<br>
                                    Email: info@safaringatezoo.com
                                </div>
                            </td>
                            <td width="40%" align="right">
                                <div class="bill-title">ADOPTION BILL</div>
                                <div>Date: ' . date('F j, Y') . '</div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">Adopter Information</div>
                    <div class="detail-row"><strong>Name:</strong> ' . htmlspecialchars($adoptions[0]['adopter_name']) . '</div>
                    <div class="detail-row"><strong>Email:</strong> ' . htmlspecialchars($adoptions[0]['adopter_email']) . '</div>
                    <div class="detail-row"><strong>Phone:</strong> ' . htmlspecialchars($adoptions[0]['phone']) . '</div>
                    <div class="detail-row"><strong>Address:</strong> ' . htmlspecialchars($adoptions[0]['address']) . '</div>
                </div>
                
                <div class="section">
                    <div class="section-title">Adopted Animals</div>
                    <div class="animal-grid">';
        
        foreach ($adoptions as $adoption) {
            $image_path = $adoption['animal_image'] ?? 'images/default-animal.jpg';
            $html .= '
                <div class="animal-item">
                    <img src="' . $image_path . '" class="animal-image" alt="' . htmlspecialchars($adoption['animal_name']) . '">
                    <div class="animal-details">
                        <strong>' . htmlspecialchars($adoption['animal_name']) . '</strong><br>
                        ' . $adoption['period_type'] . '<br>
                        ₹' . number_format($adoption['amount'], 2) . '
                    </div>
                </div>';
        }
        
        $html .= '</div></div>
                <div class="section">
                    <table class="amount-table">
                        <thead>
                            <tr>
                                <th>Animal Name</th>
                                <th>Duration</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($adoptions as $adoption) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($adoption['animal_name']) . '</td>
                    <td>' . $adoption['period_type'] . '</td>
                    <td>₹' . number_format($adoption['amount'], 2) . '</td>
                </tr>';
        }
        
        $html .= '
                            <tr class="total-row">
                                <td colspan="2" align="right"><strong>Total Amount:</strong></td>
                                <td><strong>₹' . number_format($total_amount, 2) . '</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $pdf_file = $dir . '/group_bill_' . time() . '.pdf';
        $pdf->Output($pdf_file, 'F');
        
        return $pdf_file;
    } catch (Exception $e) {
        error_log("Error generating adoption bill: " . $e->getMessage());
        throw $e;
    }
}

function sendAdoptionConfirmationEmail($adoptions, $conn) {
    try {
        // Generate single certificate and bill for all animals
        $certificate_path = generateAdoptionCertificate($adoptions);
        $bill_path = generateAdoptionBill($adoptions);
        
        global $phpmailer_available;
        
        // Build list of adopted animals for email body
        $animal_list = '';
        foreach ($adoptions as $adoption) {
            $animal_list .= '• ' . htmlspecialchars($adoption['animal_name']) . 
                          ' (' . $adoption['period_type'] . ' - ₹' . 
                          number_format($adoption['amount'], 2) . ')<br>';
        }
        
        $email_body = '
            <h2>Thank you for your adoptions!</h2>
            <p>Dear ' . htmlspecialchars($adoptions[0]['adopter_name']) . ',</p>
            <p>Thank you for adopting multiple animals. Your support helps us provide the best care for our animals.</p>
            <p><strong>Adoption Details:</strong></p>
            <p>' . $animal_list . '</p>
            <p>Please find your adoption certificate and bill attached to this email.</p>
            <p>Best regards,<br>SafariGate Zoo Team</p>
        ';
        
        if ($phpmailer_available) {
            // Use PHPMailer if available
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'sherinmariyamvarghese@gmail.com';
            $mail->Password = 'uhfo rkzj aewm kdpb';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('sherinmariyamvarghese@gmail.com', 'SafariGate Zoo');
            $mail->addAddress($adoptions[0]['adopter_email'], $adoptions[0]['adopter_name']);
            
            // Attachments
            if (file_exists($certificate_path)) {
                $mail->addAttachment($certificate_path, 'adoption_certificate.pdf');
            }
            if (file_exists($bill_path)) {
                $mail->addAttachment($bill_path, 'adoption_bill.pdf');
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'SafariGate Zoo - Multiple Animals Adoption Confirmation';
            $mail->Body = $email_body;
            
            $mail->send();
        } else {
            // Fallback to PHP's mail function if PHPMailer is not available
            $to = $adoptions[0]['adopter_email'];
            $subject = 'SafariGate Zoo - Multiple Animals Adoption Confirmation';
            
            // To send HTML mail, the Content-type header must be set
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
            $headers .= 'From: SafariGate Zoo <sherinmariyamvarghese@gmail.com>' . "\r\n";
            
            // Note: Attachments are not supported with basic mail() function
            mail($to, $subject, $email_body, $headers);
            error_log("PHPMailer not available. Using basic mail() function without attachments.");
        }
        
        // Update database with bill path for all adoptions
        foreach ($adoptions as $adoption) {
            $stmt = $conn->prepare("UPDATE adoptions SET bill_pdf_path = ? WHERE id = ?");
            $stmt->bind_param("si", $bill_path, $adoption['adoption_id']);
            $stmt->execute();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Process adoption confirmation if data is available
if (isset($_SESSION['all_adoptions'])) {
    error_log("Processing adoption emails for multiple animals");
    
    try {
        // Send single email with all adoptions
        $email_status = sendAdoptionConfirmationEmail($_SESSION['all_adoptions'], $conn);
        
        if ($email_status) {
            $_SESSION['success_message'] = "Adoption confirmation email sent successfully!";
        } else {
            $_SESSION['error_message'] = "There was an issue sending the confirmation email. Please contact support.";
        }
    } catch (Exception $e) {
        error_log("Error in adoption confirmation: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing adoption confirmation.";
    }
    
    unset($_SESSION['all_adoptions']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Adoption - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .confirmation-header h2 {
            font-size: 3rem;
            color: var(--main);
        }

        .adopter-details, .adoption-summary {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--black);
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--main);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .total-amount {
            font-size: 2.2rem;
            color: var(--main);
            font-weight: bold;
            text-align: right;
            padding: 15px;
            background: #fff8e1;
            border-radius: 8px;
            margin-top: 20px;
        }

        .btn-proceed {
            width: 100%;
            padding: 15px;
            background: var(--main);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.8rem;
            font-weight: bold;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .btn-proceed:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="confirmation-container">
        <div class="confirmation-header">
            <h2><i class="fas fa-heart"></i> Confirm Your Adoption</h2>
        </div>

        <div class="adopter-details">
            <h3 class="section-title"><i class="fas fa-user"></i> Adopter Details</h3>
            <div class="detail-row">
                <span>Name:</span>
                <span><?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <div class="detail-row">
                <span>Email:</span>
                <span><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="detail-row">
                <span>Phone:</span>
                <span><?php echo htmlspecialchars($user['phone']); ?></span>
            </div>
            <div class="detail-row">
                <span>Address:</span>
                <span><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></span>
            </div>
        </div>

        <div class="adoption-summary">
            <h3 class="section-title"><i class="fas fa-list"></i> Adoption Summary</h3>
            <?php foreach ($adoptions as $animal_id => $adoption): ?>
                <div class="detail-row">
                    <span><?php echo htmlspecialchars($adoption['name']); ?></span>
                    <span><?php echo htmlspecialchars($adoption['periodDisplay']); ?></span>
                    <span>₹<?php echo number_format($adoption['rate'], 2); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="total-amount">
                Total Amount: ₹<?php echo number_format($totalAmount, 2); ?>
            </div>
        </div>

        <form id="payment-form" method="POST" action="process_adoption.php">
            <input type="hidden" name="adoptions" value='<?php echo json_encode($adoptions); ?>'>
            <button type="button" class="btn-proceed" id="proceedToPayment">
                <i class="fas fa-lock"></i> Proceed to Payment
            </button>
        </form>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('proceedToPayment').addEventListener('click', function() {
            const options = {
                key: 'rzp_test_1TSGXPk46TbXBv',
                amount: <?php echo $totalAmount * 100; ?>,
                currency: 'INR',
                name: 'SafariGate Zoo',
                description: 'Animal Adoption Program',
                image: 'path/to/your/logo.png',
                handler: function(response) {
                    const form = document.getElementById('payment-form');
                    
                    const paymentInput = document.createElement('input');
                    paymentInput.type = 'hidden';
                    paymentInput.name = 'razorpay_payment_id';
                    paymentInput.value = response.razorpay_payment_id;
                    form.appendChild(paymentInput);
                    
                    const amountInput = document.createElement('input');
                    amountInput.type = 'hidden';
                    amountInput.name = 'total_amount';
                    amountInput.value = '<?php echo $totalAmount; ?>';
                    form.appendChild(amountInput);
                    
                    form.submit();
                },
                prefill: {
                    name: '<?php echo htmlspecialchars($user['username']); ?>',
                    email: '<?php echo htmlspecialchars($user['email']); ?>',
                    contact: '<?php echo htmlspecialchars($user['phone']); ?>'
                },
                theme: {
                    color: '#ff6e01'
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html> 
