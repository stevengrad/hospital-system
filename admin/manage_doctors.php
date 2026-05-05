<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../inc/db.php';

// Fetch departments for dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$success = '';
$error = '';

$uploadDir = __DIR__ . '/../uploads/doctors/';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $photoName = null;

    if ($name === '' || $specialty === '') {
        $error = "⚠ Please fill in all fields!";
    } else {
        // Handle photo upload
        if (!empty($_FILES['photo']['name'])) {
            $fileTmp = $_FILES['photo']['tmp_name'];
            $fileName = basename($_FILES['photo']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($ext, $allowed)) {
                $photoName = uniqid('doc_', true) . '.' . $ext;
                move_uploaded_file($fileTmp, $uploadDir . $photoName);
            } else {
                $error = "❌ Only JPG, PNG, or GIF files allowed!";
            }
        }

        if (!$error) {
            if ($id > 0) {
                // If updating, delete old photo if new uploaded
                if ($photoName) {
                    $stmt = $pdo->prepare("SELECT photo FROM doctors WHERE id=?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetchColumn();
                    if ($old && file_exists($uploadDir . $old)) {
                        unlink($uploadDir . $old);
                    }

                    $stmt = $pdo->prepare("UPDATE doctors SET name=?, specialty=?, department_id=?, photo=? WHERE id=?");
                    $stmt->execute([$name, $specialty, $department_id ?: null, $photoName, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE doctors SET name=?, specialty=?, department_id=? WHERE id=?");
                    $stmt->execute([$name, $specialty, $department_id ?: null, $id]);
                }
                $success = "✅ Doctor updated successfully!";
            } else {
                // Insert new doctor
                $stmt = $pdo->prepare("INSERT INTO doctors (name, specialty, department_id, photo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $specialty, $department_id ?: null, $photoName]);
                $success = "✅ Doctor added successfully!";
            }
        }
    }
}

// Delete doctor
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT photo FROM doctors WHERE id=?");
    $stmt->execute([$id]);
    $photo = $stmt->fetchColumn();
    if ($photo && file_exists($uploadDir . $photo)) {
        unlink($uploadDir . $photo);
    }
    $pdo->prepare("DELETE FROM doctors WHERE id=?")->execute([$id]);
    $success = "❌ Doctor deleted successfully!";
}

// Get all doctors
$stmt = $pdo->query("SELECT d.id, d.name, d.specialty, dep.name AS department, d.photo
                     FROM doctors d
                     LEFT JOIN departments dep ON d.department_id = dep.id
                     ORDER BY d.name");
$doctors = $stmt->fetchAll();

// For edit mode
$editDoctor = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id=?");
    $stmt->execute([$id]);
    $editDoctor = $stmt->fetch();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Doctors - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
form.admin-form { background:#f9f9f9; padding:20px; border-radius:10px; margin-bottom:20px; }
form.admin-form input, select { width:100%; max-width:400px; padding:8px; margin-bottom:10px; }
form.admin-form img { display:block; margin:10px 0; border-radius:8px; max-width:150px; }
table { border-collapse:collapse; width:100%; margin-top:10px; }
th, td { padding:8px; text-align:left; border:1px solid #ccc; }
th { background:#f4f6f8; }
.btn-small { font-size:13px; padding:4px 8px; }
.preview-img { max-width:150px; margin-top:10px; border-radius:8px; }
</style>
<script>
function previewImage(event) {
  const output = document.getElementById('photoPreview');
  output.src = URL.createObjectURL(event.target.files[0]);
  output.style.display = 'block';
}
</script>
</head>
<body>
<div class="container">
  <h2>Manage Doctors</h2>
  <a href="dashboard.php" class="btn">⬅ Back to Dashboard</a>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Add/Edit Doctor Form -->
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="id" value="<?= $editDoctor['id'] ?? 0 ?>">
    <label>Doctor Name</label>
    <input type="text" name="name" required value="<?= htmlspecialchars($editDoctor['name'] ?? '') ?>">

    <label>Specialty</label>
    <input type="text" name="specialty" required value="<?= htmlspecialchars($editDoctor['specialty'] ?? '') ?>">

    <label>Department</label>
    <select name="department_id">
      <option value="">-- Select Department --</option>
      <?php foreach ($departments as $dep): ?>
        <option value="<?= $dep['id'] ?>" <?= isset($editDoctor['department_id']) && $editDoctor['department_id']==$dep['id'] ? 'selected':'' ?>>
          <?= htmlspecialchars($dep['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Photo</label>
    <input type="file" name="photo" accept="image/*" onchange="previewImage(event)">
    <?php if ($editDoctor && $editDoctor['photo']): ?>
      <img src="../uploads/doctors/<?= htmlspecialchars($editDoctor['photo']) ?>" alt="Doctor Photo">
    <?php endif; ?>
    <img id="photoPreview" class="preview-img" style="display:none;" alt="Preview">

    <button type="submit" class="btn"><?= $editDoctor ? 'Update Doctor' : 'Add Doctor' ?></button>
    <?php if ($editDoctor): ?>
      <a href="manage_doctors.php" class="btn" style="background:#6c757d;">Cancel</a>
    <?php endif; ?>
  </form>

  <!-- Doctors List -->
  <table>
    <tr>
      <th>ID</th>
      <th>Photo</th>
      <th>Name</th>
      <th>Specialty</th>
      <th>Department</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($doctors as $doc): ?>
      <tr>
        <td><?= $doc['id'] ?></td>
        <td>
          <?php if ($doc['photo']): ?>
            <img src="../uploads/doctors/<?= htmlspecialchars($doc['photo']) ?>" alt="Doctor Photo" width="70">
          <?php else: ?>
            <span>No photo</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($doc['name']) ?></td>
        <td><?= htmlspecialchars($doc['specialty']) ?></td>
        <td><?= htmlspecialchars($doc['department']) ?></td>
        <td>
          <a href="?edit=<?= $doc['id'] ?>" class="btn btn-small">Edit</a>
          <a href="?delete=<?= $doc['id'] ?>" class="btn btn-small" style="background:#dc3545;" onclick="return confirm('Delete this doctor?');">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
</body>
</html>
