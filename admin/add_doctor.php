<?php
session_start();
include 'db_connect.php';

// 🔐 Admin-only access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$success     = '';
$error       = '';
$emailSent   = false;
$emailError  = '';

// ----------------------
// Helper: generate password
// ----------------------
function generateDoctorPassword(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    $random = '';
    for ($i = 0; $i < 6; $i++) {
        $random .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'doc' . $random; // always starts with "doc"
}

// ----------------------
// Load branches & specialties for the form
// ----------------------
$branches    = [];
$specialties = [];

$resB = $conn->query("SELECT BranchID, Name FROM branches ORDER BY Name");
if ($resB) {
    while ($row = $resB->fetch_assoc()) {
        $branches[] = $row;
    }
}

$resS = $conn->query("SELECT SpecialtyID, Name FROM specialties ORDER BY Name");
if ($resS) {
    while ($row = $resS->fetch_assoc()) {
        $specialties[] = $row;
    }
}

// ----------------------
// Handle form submission
// ----------------------
$generatedUsername = '';
$generatedPassword = '';
$doctorEmail       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName     = trim($_POST['first_name'] ?? '');
    $lastName      = trim($_POST['last_name'] ?? '');
    $nationalID    = trim($_POST['national_id'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $hireDate      = $_POST['hire_date'] ?? date('Y-m-d');
    $branchID      = intval($_POST['branch_id'] ?? 0);
    $specialtyID   = intval($_POST['specialty_id'] ?? 0);
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $consultFee    = floatval($_POST['consult_fee'] ?? 0.0);

    $doctorEmail = $email;

    // Basic validation
    if (
        $firstName === '' || $lastName === '' ||
        $nationalID === '' || $phone === '' || $email === '' ||
        $branchID <= 0 || $specialtyID <= 0 || $licenseNumber === ''
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^[0-9]{14}$/', $nationalID)) {
        $error = "National ID must be exactly 14 digits.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // ----------------------
        // 1) Generate unique DoctorUsername
        // ----------------------
        $baseFirst = preg_replace('/\s+/', '', strtolower($firstName));
        $baseFirst = $baseFirst !== '' ? $baseFirst : 'doc';

        do {
            $DoctorUsername = 'doc' . $baseFirst . rand(100, 999);
            $checkStmt = $conn->prepare("SELECT 1 FROM employees WHERE DoctorUsername = ? LIMIT 1");
            $checkStmt->bind_param("s", $DoctorUsername);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();
        } while ($exists);

        // ----------------------
        // 2) Generate password & hash
        // ----------------------
        $plainPassword = generateDoctorPassword();
        $PasswordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

        // ----------------------
        // 3) Insert into employees
        // employees: BranchID, NationalID, Role, FirstName, LastName,
        //            ContactPhone, Email, HireDate, DoctorUsername, PasswordHash
        // ----------------------
        $stmt = $conn->prepare("
            INSERT INTO employees
            (BranchID, NationalID, Role, FirstName, LastName, ContactPhone, Email, HireDate, DoctorUsername, PasswordHash)
            VALUES (?, ?, 'Doctor', ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt === false) {
            $error = "Database error (employees): " . $conn->error;
        } else {
            $stmt->bind_param(
                "isssssssss",
                $branchID,
                $nationalID,
                $firstName,
                $lastName,
                $phone,
                $email,
                $hireDate,
                $DoctorUsername,
                $PasswordHash
            );

            if ($stmt->execute()) {
                $newEmployeeID = $stmt->insert_id;
                $stmt->close();

                // ----------------------
                // 4) Insert into doctors
                // doctors: EmployeeID, SpecialtyID, LicenseNumber, ConsultationFee
                // ----------------------
                $stmtDoc = $conn->prepare("
                    INSERT INTO doctors (EmployeeID, SpecialtyID, LicenseNumber, ConsultationFee)
                    VALUES (?, ?, ?, ?)
                ");

                if ($stmtDoc === false) {
                    $error = "Database error (doctors): " . $conn->error;
                } else {
                    $stmtDoc->bind_param("iisd", $newEmployeeID, $specialtyID, $licenseNumber, $consultFee);
                    if ($stmtDoc->execute()) {
                        $stmtDoc->close();

                        $generatedUsername = $DoctorUsername;
                        $generatedPassword = $plainPassword;
                        $success = "Doctor created successfully!";

                        // ----------------------
                        // 5) Email credentials to doctor
                        // ----------------------
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $subject = "Your Doctor Account - Graduation Hospital";
                            $body = "Dear Dr. {$firstName} {$lastName},\n\n" .
                                    "Your doctor account has been created.\n\n" .
                                    "Login details:\n" .
                                    "Username: {$DoctorUsername}\n" .
                                    "Password: {$plainPassword}\n\n" .
                                    "You can log in at the hospital portal.\n\n" .
                                    "Please keep this information safe.\n\n" .
                                    "Regards,\nGraduation Hospital Admin";

                            $headers = "From: no-reply@graduation-hospital.local\r\n";

                            if (@mail($email, $subject, $body, $headers)) {
                                $emailSent = true;
                            } else {
                                $emailError = "Could not send email (mail server may not be configured).";
                            }
                        }

                    } else {
                        $error = "Error saving doctor details: " . $stmtDoc->error;
                    }
                }
            } else {
                $error = "Error saving employee: " . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Doctor</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,#0097e6,#00bcd4);
    color:#fff;
    padding:40px;
}
.container {
    max-width:800px;
    margin:0 auto;
    background:rgba(255,255,255,0.1);
    padding:25px 30px;
    border-radius:12px;
    backdrop-filter:blur(6px);
}
h1 { text-align:center; margin-bottom:20px; }
label { display:block; margin-top:10px; font-weight:600; }
input, select {
    width:100%;
    padding:8px 10px;
    border-radius:6px;
    border:1px solid rgba(255,255,255,0.5);
    margin-top:4px;
}
.row {
    display:flex;
    gap:15px;
}
.row > div { flex:1; }
button {
    margin-top:15px;
    padding:10px 18px;
    border:none;
    border-radius:8px;
    background:#00a8ff;
    color:#fff;
    font-weight:600;
    cursor:pointer;
}
button:hover { background:#0091e0; }
.msg-success {
    background:#d4edda;
    color:#155724;
    padding:10px;
    border-radius:8px;
    margin-bottom:10px;
}
.msg-error {
    background:#f8d7da;
    color:#721c24;
    padding:10px;
    border-radius:8px;
    margin-bottom:10px;
}
.credentials {
    background:#fff;
    color:#333;
    padding:12px 14px;
    border-radius:8px;
    margin-top:10px;
    font-family:monospace;
}
.credentials-buttons {
    margin-top:10px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.credentials-buttons button {
    background:#007bff;
    padding:6px 12px;
    font-size:14px;
}
.credentials-buttons button.print-btn {
    background:#28a745;
}
.credentials-buttons button.email-info-btn {
    background:#6c5ce7;
}
a.btn-back {
    display:inline-block;
    margin-bottom:10px;
    color:#fff;
    text-decoration:none;
    background:#273c75;
    padding:6px 12px;
    border-radius:6px;
}
a.btn-back:hover { background:#192a56; }

@media print {
    body {
        background:#fff;
        padding:0;
    }
    .container {
        box-shadow:none;
        background:#fff;
    }
    .no-print {
        display:none !important;
    }
}
</style>
</head>
<body>
<div class="container">
    <a href="manage_doctors.php" class="btn-back no-print">⬅ Back to Manage Doctors</a>
    <h1>Add New Doctor</h1>

    <?php if ($error): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="msg-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($emailSent): ?>
        <div class="msg-success">✅ Credentials emailed to: <?= htmlspecialchars($doctorEmail); ?></div>
    <?php elseif ($emailError): ?>
        <div class="msg-error">⚠️ <?= htmlspecialchars($emailError); ?></div>
    <?php endif; ?>

    <?php if ($generatedUsername && $generatedPassword): ?>
        <div id="cred-box" class="credentials">
            <strong>Doctor Login Credentials (save these now):</strong><br>
            Username: <span id="cred-username"><?= htmlspecialchars($generatedUsername) ?></span><br>
            Password: <span id="cred-password"><?= htmlspecialchars($generatedPassword) ?></span>
        </div>

        <div class="credentials-buttons no-print">
            <button type="button" onclick="copyPassword()">📋 Copy Password</button>
            <button type="button" class="print-btn" onclick="printCredentials()">📄 Print / Save as PDF</button>
            <button type="button" class="email-info-btn" onclick="alertPdfInfo()">🧾 How to Save as PDF</button>
        </div>
    <?php endif; ?>

    <form method="post" class="no-print">
        <div class="row">
            <div>
                <label>First Name *</label>
                <input type="text" name="first_name" required>
            </div>
            <div>
                <label>Last Name *</label>
                <input type="text" name="last_name" required>
            </div>
        </div>

        <div class="row">
            <div>
                <label>National ID (14 digits) *</label>
                <input type="text" name="national_id" maxlength="14" required>
            </div>
            <div>
                <label>Phone *</label>
                <input type="text" name="phone" required>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Hire Date</label>
                <input type="date" name="hire_date" value="<?= date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label>Branch *</label>
                <select name="branch_id" required>
                    <option value="">-- Select Branch --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['BranchID']; ?>">
                            <?= htmlspecialchars($b['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Specialty *</label>
                <select name="specialty_id" required>
                    <option value="">-- Select Specialty --</option>
                    <?php foreach ($specialties as $s): ?>
                        <option value="<?= (int)$s['SpecialtyID']; ?>">
                            <?= htmlspecialchars($s['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div>
                <label>License Number *</label>
                <input type="text" name="license_number" required>
            </div>
            <div>
                <label>Consultation Fee (EGP)</label>
                <input type="number" step="0.01" name="consult_fee" value="0.00">
            </div>
        </div>

        <button type="submit">Save Doctor</button>
    </form>
</div>

<script>
// Copy password to clipboard
function copyPassword() {
    const pw = document.getElementById('cred-password').innerText;
    navigator.clipboard.writeText(pw).then(() => {
        alert('Password copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy password.');
    });
}

// Print only the credentials (or whole page, but styled)
function printCredentials() {
    window.print();
}

// Info dialog about saving as PDF
function alertPdfInfo() {
    alert('To save the credentials as a PDF:\n\n1. Click the "Print / Save as PDF" button.\n2. In the print dialog, choose "Save as PDF" as the printer.\n3. Click Save and choose a location on your computer.');
}
</script>

</body>
</html>
