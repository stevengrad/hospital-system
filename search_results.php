<?php
session_start();
include "db_connect.php";

if (!isset($_GET['q']) || trim($_GET['q']) === '') {
    die("<h3>Please enter a search term.</h3>");
}

$q      = trim($_GET['q']);
$q_like = "%" . $q . "%";

/* ==========================
   1) DOCTOR SEARCH
   ========================== */

$doctor_sql = "
    SELECT 
        e.EmployeeID,
        e.FirstName,
        e.LastName,
        e.HireDate,
        s.Name AS SpecialtyName,
        d.ConsultationFee
    FROM employees e
    INNER JOIN doctors d     ON e.EmployeeID = d.EmployeeID
    INNER JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
    WHERE 
        CONCAT(e.FirstName, ' ', e.LastName) LIKE ?   -- full name (Ali Mostafa)
        OR e.FirstName LIKE ?                         -- first name
        OR e.LastName LIKE ?                          -- last name
        OR s.Name LIKE ?                              -- specialty
";

$stmt_doc = $conn->prepare($doctor_sql);
$stmt_doc->bind_param("ssss", $q_like, $q_like, $q_like, $q_like);
$stmt_doc->execute();
$doctor_result = $stmt_doc->get_result();

/* Fetch doctors into array & collect their specialties */
$doctors = [];
$specialtiesFromDoctors = [];

while ($row = $doctor_result->fetch_assoc()) {
    $doctors[] = $row;
    if (!empty($row['SpecialtyName'])) {
        $specialtiesFromDoctors[] = $row['SpecialtyName'];
    }
}
$specialtiesFromDoctors = array_unique($specialtiesFromDoctors);

/* ==========================
   2) SERVICE SEARCH
   ========================== */

/*
   Logic:
   - If we found doctors, search services using their specialties.
     e.g. Doctor Ali Mostafa -> Cardiology -> find Cardiology Consultation.
   - If no doctors were found, fall back to searching with the raw query text.
*/

$services = [];

if (!empty($specialtiesFromDoctors)) {
    // Build dynamic WHERE with all specialties
    $placeholders = [];
    $params       = [];
    $types        = '';

    foreach ($specialtiesFromDoctors as $spec) {
        $placeholders[] = "Name LIKE ?";
        $params[]       = "%" . $spec . "%";
        $types         .= 's';
    }

    $service_sql = "
        SELECT SpecialtyID AS ServiceID, Name AS ServiceName, '' AS Description, 0 AS Price
        FROM specialties
        WHERE " . implode(' OR ', $placeholders). " ORDER BY Name ASC ";

    $stmt_srv = $conn->prepare($service_sql);
    $stmt_srv->bind_param($types, ...$params);
    $stmt_srv->execute();
    $service_result = $stmt_srv->get_result();

    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }
} else {
    // No doctors found; search services by the query text itself
    $service_sql = "
        SELECT SpecialtyID AS ServiceID, Name AS ServiceName,'' AS Description, 0 AS Price
        FROM specialties
        WHERE Name LIKE ?
        ORDER BY Name ASC
    ";
    $stmt_srv = $conn->prepare($service_sql);
    $stmt_srv->bind_param("s", $q_like);
    $stmt_srv->execute();
    $service_result = $stmt_srv->get_result();

    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color:#f4f4f4; padding:20px; }
        .section-box {
            background:#fff;
            border-radius:10px;
            padding:20px;
            margin-bottom:25px;
            box-shadow:0 3px 10px rgba(0,0,0,0.1);
        }
        .doctor-card img {
            width:80px;
            height:80px;
            border-radius:50%;
            object-fit:cover;
            margin-bottom:8px;
        }
    </style>
</head>
<body>

<div class="container">

    <h2 class="mb-4">
        Search Results for:
        <span class="text-primary"><?= htmlspecialchars($q) ?></span>
    </h2>

    <!-- =============== DOCTORS =============== -->
    <div class="section-box">
        <h4 class="text-primary mb-3">Doctors</h4>

        <?php if (!empty($doctors)): ?>
            <div class="row">
                <?php foreach ($doctors as $doc): ?>
                    <?php
                        // Experience from HireDate
                        $experience = "N/A";
                        if (!empty($doc['HireDate']) && $doc['HireDate'] !== '0000-00-00') {
                            $hire = new DateTime($doc['HireDate']);
                            $now  = new DateTime();
                            $experience = $now->diff($hire)->y . " yrs";
                        }
                        $fullName  = trim($doc['FirstName'] . " " . $doc['LastName']);
                        $specialty = $doc['SpecialtyName'] ?? 'Specialty';
                    ?>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center p-3 doctor-card">
                            <!-- Static default image (no photo column in DB) -->
                            <img src="assets/img/default-doctor.png" alt="Doctor">

                            <h6 class="fw-bold">
                                Dr. <?= htmlspecialchars($fullName ?: 'Doctor') ?>
                            </h6>

                            <p class="mb-1">
                                <strong>Specialty:</strong> <?= htmlspecialchars($specialty) ?>
                            </p>

                            <p class="small mb-1">
                                <strong>Experience:</strong> <?= $experience ?>
                            </p>

                            <p class="small mb-0">
                                <strong>Consultation Fee:</strong>
                                <?= number_format((float)$doc['ConsultationFee'], 2) ?> EGP
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No doctors found.</p>
        <?php endif; ?>
    </div>

    <!-- =============== SERVICES =============== -->
    <div class="section-box">
        <h4 class="text-primary mb-3">Services</h4>

        <?php if (!empty($services)): ?>
            <div class="row">
                <?php foreach ($services as $srv): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card p-3">
                            <h5 class="text-primary">
                                <?= htmlspecialchars($srv['ServiceName']) ?>
                            </h5>
                            <p class="mb-1">
                                <?= htmlspecialchars($srv['Description']) ?>

                            <p class="fw-bold mb-0">
                                Price: <?= number_format((float)$srv['Price'], 2) ?> EGP
                            </p>

                            <a
                                href="book_appointment.php?specialty_id=<?= (int)$srv['ServiceID'] ?>" 
                                class="btn btn-primary mt-3"
                                >
                                Book Appointment
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No services found.</p>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="btn btn-secondary mt-2">⬅ Back to Dashboard</a>

</div>

</body>
</html>
