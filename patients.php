<?php
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // main login page
    exit();
}

include('db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Cairo Hospitals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9fafc;
            font-family: "Poppins", sans-serif;
        }
        .navbar {
            background-color: #007bff;
        }
        .navbar-brand, .nav-link, .navbar-text {
            color: #fff !important;
        }
        table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">🏥 Cairo Hospitals</a>
    <div class="d-flex">
        <a href="dashboard.php" class="btn btn-light btn-sm me-2">Dashboard</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container mt-5">
    <h2 class="text-center mb-4">Registered Patients</h2>

    <?php
    // Fetch patient data from real patients table
    $sql = "
        SELECT 
            PatientID,
            FirstName,
            LastName,
            Email,
            NationalID,
            DOB,
            ContactPhone,
            Address
        FROM patients
        ORDER BY PatientID ASC
    ";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover text-center">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>National ID</th>
                        <th>DOB</th>
                        <th>Phone</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        $fullName = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['PatientID']); ?></td>
                        <td><?= htmlspecialchars($fullName !== '' ? $fullName : 'Unknown'); ?></td>
                        <td><?= htmlspecialchars($row['Email'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['NationalID'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['DOB'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['ContactPhone'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['Address'] ?? ''); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">No patients found.</div>
    <?php endif; ?>
</div>

</body>
</html>
