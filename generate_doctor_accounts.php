<?php
die("❌ Disabled script. Remove this line only if you want to generate accounts again.");

// One-time script to fill DoctorUsername and PasswordHash
// for existing doctors in the employees table.

include 'db_connect.php';

// Generate password starting with "doc" + 6 random strong characters
function generateDoctorPassword(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    $random = '';
    for ($i = 0; $i < 6; $i++) {
        $random .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'doc' . $random;
}

echo "<h2>Generating doctor accounts...</h2>";

// Get all doctors that still have NULL username or password
$sql = "
    SELECT EmployeeID, FirstName, LastName
    FROM employees
    WHERE Role = 'Doctor'
      AND (DoctorUsername IS NULL OR DoctorUsername = '')
";
$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

if ($result->num_rows === 0) {
    echo "<p>No doctors need accounts (all already have DoctorUsername).</p>";
    exit;
}

while ($row = $result->fetch_assoc()) {
    $employeeId = (int)$row['EmployeeID'];
    $firstName  = $row['FirstName'];
    $lastName   = $row['LastName'];

    // Username: "doc" + lowercase firstname + EmployeeID (to ensure uniqueness)
    $baseFirst  = preg_replace('/\s+/', '', strtolower($firstName));
    $doctorUsername = 'doc' . $baseFirst . $employeeId;

    // Password: doc + random
    $plainPassword = generateDoctorPassword();
    $passwordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Update row
    $stmt = $conn->prepare("
        UPDATE employees
        SET DoctorUsername = ?, PasswordHash = ?
        WHERE EmployeeID = ?
    ");
    if (!$stmt) {
        echo "<p style='color:red;'>Prepare failed for EmployeeID $employeeId: " . $conn->error . "</p>";
        continue;
    }
    $stmt->bind_param("ssi", $doctorUsername, $passwordHash, $employeeId);
    if ($stmt->execute()) {
        echo "<p><strong>EmployeeID {$employeeId}</strong> ({$firstName} {$lastName})<br>
              Username: <b>{$doctorUsername}</b><br>
              Password: <b>{$plainPassword}</b></p><hr>";
    } else {
        echo "<p style='color:red;'>Update failed for EmployeeID $employeeId: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

echo "<p><b>Done!</b> Copy these usernames & passwords for your records, then delete or secure this file.</p>";
