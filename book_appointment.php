<?php
session_start();
include 'db_connect.php';

/* =========================
   Language
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

/* =========================
   Auth Check
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?lang=" . urlencode($lang));
    exit();
}

$login_id = (int)$_SESSION['user_id'];
$selected_specialty_id = isset($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : 0;

$error = "";
$success = "";
$patient_id = 0;
$national_id = "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function table_has_column($conn, $table, $column) {
    $table = str_replace("`", "", $table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

function first_existing_column($conn, $table, $columns) {
    foreach ($columns as $col) {
        if (table_has_column($conn, $table, $col)) {
            return $col;
        }
    }
    return null;
}

/* =========================
   Get PatientID from login national_id
========================= */
$stmt = $conn->prepare("SELECT national_id FROM login WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $login_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $national_id = $row['national_id'] ?? "";
}
$stmt->close();

if (!empty($national_id)) {
    $stmt = $conn->prepare("SELECT PatientID FROM patients WHERE NationalID = ? LIMIT 1");
    $stmt->bind_param("s", $national_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $patient_id = (int)$row['PatientID'];
        $_SESSION['patient_id'] = $patient_id;
    }
    $stmt->close();
}

if ($patient_id <= 0) {
    $error = "No PatientID linked to this account. Check NationalID in login and patients tables.";
}

/* =========================
   Direct Booking Without API
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient_id > 0) {
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    $start_raw = trim($_POST['start'] ?? "");

    if ($doctor_id <= 0 || $branch_id <= 0 || $start_raw === "") {
        $error = "Please select doctor, branch, and appointment time.";
    } else {
        $start_clean = str_replace("T", " ", $start_raw);

        if (strlen($start_clean) === 16) {
            $start_clean .= ":00";
        }

        $timestamp = strtotime($start_clean);

        if (!$timestamp) {
            $error = "Invalid appointment date.";
        } else {
            $appointment_datetime = date("Y-m-d H:i:s", $timestamp);

            if ($timestamp < time()) {
                $error = "You cannot book an appointment in the past.";
            }
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("
            SELECT AppointmentID 
            FROM appointments 
            WHERE DoctorID = ? 
              AND AppointmentDateTime = ? 
              AND Status <> 'Canceled'
            LIMIT 1
        ");
        $stmt->bind_param("is", $doctor_id, $appointment_datetime);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check && $check->num_rows > 0) {
            $error = "This appointment slot is already booked. Please choose another time.";
        }

        $stmt->close();
    }

    if (empty($error)) {
        $branchColumn = first_existing_column($conn, "appointments", [
            "BranchID",
            "branch_id",
            "BranchId"
        ]);

        $status = "Pending";

        if ($branchColumn) {
            $safeBranchColumn = str_replace("`", "", $branchColumn);

            $sql = "
                INSERT INTO appointments 
                    (PatientID, DoctorID, `$safeBranchColumn`, AppointmentDateTime, Status)
                VALUES 
                    (?, ?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iiiss",
                $patient_id,
                $doctor_id,
                $branch_id,
                $appointment_datetime,
                $status
            );
        } else {
            $sql = "
                INSERT INTO appointments 
                    (PatientID, DoctorID, AppointmentDateTime, Status)
                VALUES 
                    (?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iiss",
                $patient_id,
                $doctor_id,
                $appointment_datetime,
                $status
            );
        }

        if ($stmt && $stmt->execute()) {
            $stmt->close();
            header("Location: my_appointments.php?booked=1&lang=" . urlencode($lang));
            exit();
        } else {
            $error = "Database booking failed: " . $conn->error;
        }
    }
}

/* =========================
   Fetch Specialties
========================= */
$specialties = [];
$res = $conn->query("SELECT SpecialtyID, Name FROM specialties ORDER BY Name ASC");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $specialties[] = $row;
    }
}

/* =========================
   Fetch Doctors
========================= */
$doctors = [];

$sql = "
    SELECT 
        d.EmployeeID AS DoctorID,
        d.SpecialtyID,
        CONCAT(e.FirstName, ' ', e.LastName) AS DoctorName,
        s.Name AS SpecialtyName
    FROM doctors d
    INNER JOIN employees e ON d.EmployeeID = e.EmployeeID
    LEFT JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
";

if ($selected_specialty_id > 0) {
    $sql .= " WHERE d.SpecialtyID = ? ";
}

$sql .= " ORDER BY DoctorName ASC";

$stmt = $conn->prepare($sql);

if ($selected_specialty_id > 0) {
    $stmt->bind_param("i", $selected_specialty_id);
}

$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $doctors[] = $row;
}

$stmt->close();

/* =========================
   Fetch Branches
========================= */
$branches = [];

$branchNameColumn = first_existing_column($conn, "branches", [
    "BranchName",
    "Name",
    "branch_name",
    "Location",
    "Address"
]);

if ($branchNameColumn) {
    $safeCol = str_replace("`", "", $branchNameColumn);
    $sql = "SELECT BranchID, `$safeCol` AS BranchName FROM branches ORDER BY BranchName ASC";
} else {
    $sql = "SELECT BranchID, CONCAT('Branch ', BranchID) AS BranchName FROM branches ORDER BY BranchID ASC";
}

$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $branches[] = $row;
    }
}

$minDateTime = date("Y-m-d\TH:i");
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0b141e;
            color: #fff;
            padding: 30px;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: #1c2a39;
            padding: 30px;
            border-radius: 20px;
        }

        h1 {
            margin-top: 0;
            color: #70d1f4;
        }

        .top-links {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
        }

        .top-links a {
            color: #fff;
            background: #334155;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
        }

        .alert {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .error {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
            border: 1px solid #ef4444;
        }

        .success {
            background: rgba(34, 197, 94, 0.2);
            color: #bbf7d0;
            border: 1px solid #22c55e;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select,
        input {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            border: none;
            margin-bottom: 18px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #70d1f4;
            color: #0b141e;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #38bdf8;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .note {
            color: #94a3b8;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top-links">
            <a href="dashboard.php?lang=<?= urlencode($lang) ?>">Dashboard</a>
            <a href="my_appointments.php?lang=<?= urlencode($lang) ?>">My Appointments</a>
            <a href="services.php?lang=<?= urlencode($lang) ?>">Services</a>
        </div>

        <h1>Book Appointment</h1>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if (empty($doctors)): ?>
            <div class="alert error">No doctors found for this specialty.</div>
        <?php endif; ?>

        <?php if (empty($branches)): ?>
            <div class="alert error">No branches found.</div>
        <?php endif; ?>

        <form method="POST" action="book_appointment.php?lang=<?= urlencode($lang) ?><?= $selected_specialty_id > 0 ? '&specialty_id=' . (int)$selected_specialty_id : '' ?>">
            
            <label for="specialty_id">Specialty</label>
            <select id="specialty_id" onchange="changeSpecialty(this.value)">
                <option value="0">All Specialties</option>

                <?php foreach ($specialties as $sp): ?>
                    <option value="<?= (int)$sp['SpecialtyID'] ?>" <?= ((int)$sp['SpecialtyID'] === $selected_specialty_id) ? 'selected' : '' ?>>
                        <?= h($sp['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="doctor_id">Doctor</label>
            <select name="doctor_id" id="doctor_id" required>
                <option value="">Select Doctor</option>

                <?php foreach ($doctors as $doctor): ?>
                    <option value="<?= (int)$doctor['DoctorID'] ?>">
                        Dr. <?= h($doctor['DoctorName']) ?>
                        <?= !empty($doctor['SpecialtyName']) ? ' - ' . h($doctor['SpecialtyName']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="branch_id">Branch</label>
            <select name="branch_id" id="branch_id" required>
                <option value="">Select Branch</option>

                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int)$branch['BranchID'] ?>">
                        <?= h($branch['BranchName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="start">Appointment Date & Time</label>
            <input 
                type="datetime-local" 
                name="start" 
                id="start" 
                min="<?= h($minDateTime) ?>" 
                required
            >

            <button type="submit" <?= (!empty($error) || empty($doctors) || empty($branches)) ? 'disabled' : '' ?>>
                Confirm Booking
            </button>

            <div class="note">
                This booking is saved directly into the appointments table without API.
            </div>
        </form>
    </div>

    <script>
        function changeSpecialty(id) {
            const lang = <?= json_encode($lang) ?>;
            window.location.href = "book_appointment.php?lang=" + encodeURIComponent(lang) + "&specialty_id=" + encodeURIComponent(id);
        }
    </script>
</body>
</html>