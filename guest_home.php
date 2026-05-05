<?php
session_start();
include('db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cairo Hospitals - Guest Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f9fafc; font-family: "Poppins", sans-serif; }
    .navbar { background-color: #007bff; }
    .navbar-brand, .nav-link, .navbar-text { color: #fff !important; }
    .hero {
        background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('hospital_bg.jpg') center/cover no-repeat;
        color: #fff; text-align: center; padding: 100px 20px; border-radius: 15px;
    }
    .section-title { font-weight: 600; color: #007bff; margin-bottom: 25px; }
    .card { border-radius: 15px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
  </style>
</head>
<body>

<!-- 🔷 Navigation -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="guest_home.php">🏥 Cairo Hospitals</a>
    <div class="d-flex">
      <!-- Login goes to index.php (main login) -->
             <a href="contact.php" class="btn btn-warning btn-sm me-2">Contact Us</a>
      <a href="index.php" class="btn btn-light btn-sm me-2">Login</a>
      <a href="register.php" class="btn btn-outline-light btn-sm">Register</a>
    </div>
  </div>
</nav>

<!-- 🏠 Hero Section -->
<section class="hero mt-4 mb-5">
  <div class="container">
    <h1 class="fw-bold">Welcome to Cairo Hospitals</h1>
    <p class="lead">Your health is our top priority. Explore our doctors and services below.</p>
    <a href="#services" class="btn btn-primary mt-3">View Services</a>
  </div>
</section>

<div class="container">
  <!-- 💊 Services Section (static – no services table in DB) -->
  <h2 id="services" class="section-title text-center">Our Services</h2>
  <div class="row g-4 mb-5">

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">General Check-up</h5>
        <p class="text-muted">Routine examinations to keep track of your overall health.</p>
        <p><strong>Price:</strong> 250.00 EGP</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">Cardiology</h5>
        <p class="text-muted">Specialized care for heart and blood vessel conditions.</p>
        <p><strong>Price:</strong> 400.00 EGP</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">Pediatrics</h5>
        <p class="text-muted">Comprehensive medical services for children and infants.</p>
        <p><strong>Price:</strong> 300.00 EGP</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">Laboratory Tests</h5>
        <p class="text-muted">Accurate lab tests and diagnostics for all age groups.</p>
        <p><strong>Price:</strong> 150.00 EGP</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">Radiology</h5>
        <p class="text-muted">X-rays, CT scans, and ultrasound imaging services.</p>
        <p><strong>Price:</strong> 350.00 EGP</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 text-center">
        <h5 class="card-title text-primary">Emergency Care</h5>
        <p class="text-muted">24/7 emergency services for urgent medical situations.</p>
        <p><strong>Price:</strong> Depends on case</p>
        <a href="index.php" class="btn btn-outline-primary">Login to Book</a>
      </div>
    </div>

  </div>

  <!-- 👨‍⚕️ Doctors Section (real data from doctors + employees + specialties + branches) -->
  <h2 class="section-title text-center">Our Doctors</h2>
  <div class="row g-4">
    <?php
    // Join doctors, employees, specialties, branches
    $doc_sql = "
        SELECT 
            d.EmployeeID,
            CONCAT(e.FirstName, ' ', e.LastName) AS DoctorName,
            s.Name AS SpecialtyName,
            d.ConsultationFee,
            b.Name AS BranchName
        FROM doctors d
        INNER JOIN employees e ON d.EmployeeID = e.EmployeeID
        INNER JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
        INNER JOIN branches b ON e.BranchID = b.BranchID
        ORDER BY DoctorName ASC
    ";
    $doctors = $conn->query($doc_sql);

    if ($doctors && $doctors->num_rows > 0):
      while ($doc = $doctors->fetch_assoc()):
          $name          = htmlspecialchars($doc['DoctorName'] ?? 'Unknown Doctor');
          $specialty     = htmlspecialchars($doc['SpecialtyName'] ?? 'General Medicine');
          $branch        = htmlspecialchars($doc['BranchName'] ?? 'Main Branch');
          $fee           = isset($doc['ConsultationFee']) ? number_format($doc['ConsultationFee'], 2) : '0.00';
    ?>
    <div class="col-md-3">
      <div class="card text-center p-3">
        <!-- No photo in DB, use a default image -->
        <img src="assets/img/default-doctor.png"
             class="img-fluid rounded-circle mx-auto mb-3"
             alt="Doctor"
             style="width:100px;height:100px;object-fit:cover;">
        <h6 class="fw-bold"><?= $name; ?></h6>
        <p class="text-muted mb-1"><?= $specialty; ?></p>
        <p class="small mb-1">Branch: <?= $branch; ?></p>
        <p class="small">Consultation: <?= $fee; ?> EGP</p>
      </div>
    </div>
    <?php endwhile; else: ?>
      <div class="alert alert-info text-center">No doctors added yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- 🦶 Footer -->
<footer class="text-center mt-5 py-3 bg-light">
  <p class="mb-0">© <?= date('Y'); ?> Cairo Hospitals — All Rights Reserved</p>
</footer>

</body>
</html>
