<?php
session_start();
require_once "db_connect.php";

$message = "";
$messageType = "";
$details = [];
$submitted = false;

function safeName($name) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $name);
}

function getDatabaseName($conn) {
    $res = $conn->query("SELECT DATABASE() AS dbname");
    $row = $res->fetch_assoc();
    return $row["dbname"] ?? "";
}

function tableHasColumn($conn, $db, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("sss", $db, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return ((int)($row["total"] ?? 0)) > 0;
}

function deleteByColumn($conn, $table, $column, $value, &$details) {
    if ($value === "" || $value === null) {
        return 0;
    }

    if (!safeName($table) || !safeName($column)) {
        return 0;
    }

    $sql = "DELETE FROM `$table` WHERE `$column` = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $value = (string)$value;
    $stmt->bind_param("s", $value);
    $stmt->execute();

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted > 0) {
        $details[] = "Deleted $deleted row(s) from `$table` using `$column`.";
    }

    return $deleted;
}

function deleteByColumnIn($conn, $table, $column, $values, &$details) {
    if (empty($values)) {
        return 0;
    }

    if (!safeName($table) || !safeName($column)) {
        return 0;
    }

    $totalDeleted = 0;

    foreach ($values as $value) {
        if ($value === "" || $value === null) {
            continue;
        }

        $sql = "DELETE FROM `$table` WHERE `$column` = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            continue;
        }

        $value = (string)$value;
        $stmt->bind_param("s", $value);
        $stmt->execute();

        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted > 0) {
            $totalDeleted += $deleted;
        }
    }

    if ($totalDeleted > 0) {
        $details[] = "Deleted $totalDeleted row(s) from `$table` using `$column`.";
    }

    return $totalDeleted;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted = true;
    $username = trim($_POST["username"] ?? "");

    if ($username === "") {
        $message = "Please enter a username.";
        $messageType = "error";
    } else {
        try {
            $conn->begin_transaction();

            $db = getDatabaseName($conn);

            if ($db === "") {
                throw new Exception("Could not detect current database.");
            }

            /*
                Get the exact user only by username.
                IMPORTANT: We do NOT delete by email.
            */
            $stmtUser = $conn->prepare("
                SELECT id, username, national_id
                FROM registration
                WHERE username = ?
                LIMIT 1
            ");

            if (!$stmtUser) {
                throw new Exception("Could not prepare user lookup query.");
            }

            $stmtUser->bind_param("s", $username);
            $stmtUser->execute();
            $userResult = $stmtUser->get_result();
            $user = $userResult->fetch_assoc();
            $stmtUser->close();

            $userExistsInDatabase = false;
            $registrationId = "";
            $nationalId = "";
            $patientIds = [];

if (!$user) {
    $details[] = "Username was not found in registration table. Database deletion skipped.";
    $details[] = "Trying to delete face data from S3 and representations.pkl only.";
} else {
    $userExistsInDatabase = true;
    $registrationId = (string)$user["id"];
    $nationalId = trim((string)($user["national_id"] ?? ""));
}

            /*
                Find exact patient IDs only by username or national_id.
                No email matching here.
            */
            

            if (tableHasColumn($conn, $db, "patients", "id")) {
                if (tableHasColumn($conn, $db, "patients", "username")) {
                    $stmtPatient = $conn->prepare("
                        SELECT id
                        FROM patients
                        WHERE username = ?
                    ");
                    if ($stmtPatient) {
                        $stmtPatient->bind_param("s", $username);
                        $stmtPatient->execute();
                        $resPatient = $stmtPatient->get_result();

                        while ($row = $resPatient->fetch_assoc()) {
                            $patientIds[] = (string)$row["id"];
                        }

                        $stmtPatient->close();
                    }
                }

                if ($nationalId !== "" && tableHasColumn($conn, $db, "patients", "national_id")) {
                    $stmtPatient = $conn->prepare("
                        SELECT id
                        FROM patients
                        WHERE national_id = ?
                    ");
                    if ($stmtPatient) {
                        $stmtPatient->bind_param("s", $nationalId);
                        $stmtPatient->execute();
                        $resPatient = $stmtPatient->get_result();

                        while ($row = $resPatient->fetch_assoc()) {
                            $patientIds[] = (string)$row["id"];
                        }

                        $stmtPatient->close();
                    }
                }
            }

            $patientIds = array_values(array_unique($patientIds));

            /*
                Get all tables that contain safe identifiers.
                IMPORTANT: email is intentionally NOT included.
            */
            $stmtTables = $conn->prepare("
                SELECT DISTINCT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND COLUMN_NAME IN ('username', 'national_id', 'user_id', 'patient_id')
            ");

            if (!$stmtTables) {
                throw new Exception("Could not prepare tables lookup query.");
            }

            $stmtTables->bind_param("s", $db);
            $stmtTables->execute();
            $tablesResult = $stmtTables->get_result();

            $tables = [];

            while ($row = $tablesResult->fetch_assoc()) {
                $tableName = $row["TABLE_NAME"];

                if (safeName($tableName)) {
                    $tables[] = $tableName;
                }
            }

            $stmtTables->close();

            if (empty($tables)) {
                throw new Exception("No related tables found.");
            }

            $conn->query("SET FOREIGN_KEY_CHECKS = 0");

            $totalDeleted = 0;

            foreach ($tables as $table) {
                if (tableHasColumn($conn, $db, $table, "username")) {
                    $totalDeleted += deleteByColumn($conn, $table, "username", $username, $details);
                }

                if ($nationalId !== "" && tableHasColumn($conn, $db, $table, "national_id")) {
                    $totalDeleted += deleteByColumn($conn, $table, "national_id", $nationalId, $details);
                }

                if (tableHasColumn($conn, $db, $table, "user_id")) {
                    $totalDeleted += deleteByColumn($conn, $table, "user_id", $registrationId, $details);
                }

                if (!empty($patientIds) && tableHasColumn($conn, $db, $table, "patient_id")) {
                    $totalDeleted += deleteByColumnIn($conn, $table, "patient_id", $patientIds, $details);
                }
            }

            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            /*
                Delete face data using Face API.
                This deletes from representations.pkl and tries S3 prefix deletion.
            */
            $faceMessage = "";
            $faceDeleteUrl = getenv("FACE_DELETE_URL") ?: "http://face-api:5001/face/delete_user";

            if (!empty($faceDeleteUrl)) {
                $payload = json_encode([
                    "username" => $username,
                    "bucket"   => "cairo-hospital-face-images-137068224200",
                    "prefix"   => "faces"
                ]);

                $ch = curl_init($faceDeleteUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $faceJson = json_decode($response, true);

                if (!$curlError && $httpCode >= 200 && $httpCode < 300 && !empty($faceJson["success"])) {
                    $pklRemoved = $faceJson["representations_removed"] ?? 0;
                    $s3Deleted = $faceJson["images_deleted_by_prefix"] ?? 0;

                    $faceMessage = " Face deletion completed. PKL removed: $pklRemoved. S3 images deleted: $s3Deleted.";
                } else {
                    $faceMessage = " Database deleted, but Face API deletion failed.";

                    if ($curlError) {
                        $faceMessage .= " CURL error: " . $curlError . ".";
                    }

                    if (!empty($response)) {
                        $faceMessage .= " API response: " . $response . ".";
                    }
                }
            }

            $conn->commit();

            $message = "User `$username` deleted safely. Total database rows deleted: $totalDeleted." . $faceMessage;
            $messageType = "success";

        } catch (Exception $e) {
            $conn->rollback();
            @$conn->query("SET FOREIGN_KEY_CHECKS = 1");

            $message = "Delete failed: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delete User Safely | Cairo Hospitals</title>
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png?v=2">

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #08111f, #12385f);
    display: flex;
    justify-content: center;
    align-items: center;
}

.card {
    width: 650px;
    background: #f8fbff;
    border-radius: 24px;
    padding: 35px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
}

.logo {
    width: 76px;
    height: 76px;
    margin: 0 auto 15px;
    border-radius: 20px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid #dbeafe;
}

.logo img {
    width: 68px;
    height: 68px;
    object-fit: contain;
}

h1 {
    text-align: center;
    color: #146c33;
    margin-bottom: 10px;
}

p {
    text-align: center;
    color: #64748b;
    line-height: 1.6;
}

.warning {
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fed7aa;
    padding: 13px;
    border-radius: 12px;
    margin-bottom: 15px;
    text-align: center;
    font-weight: bold;
}

label {
    display: block;
    margin-top: 20px;
    margin-bottom: 8px;
    font-weight: bold;
    color: #243b53;
}

input {
    width: 100%;
    height: 55px;
    border-radius: 14px;
    border: 1px solid #dbe4ee;
    padding: 0 15px;
    font-size: 16px;
    box-sizing: border-box;
}

button {
    width: 100%;
    height: 55px;
    margin-top: 20px;
    border: none;
    border-radius: 14px;
    background: #dc2626;
    color: white;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #b91c1c;
}

.success {
    background: #ecfdf3;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 15px;
    font-weight: bold;
    text-align: center;
}

.error {
    background: #fff1f2;
    color: #be123c;
    border: 1px solid #fecdd3;
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 15px;
    font-weight: bold;
    text-align: center;
}

.details {
    background: #f1f5f9;
    border: 1px solid #dbe4ee;
    border-radius: 12px;
    padding: 14px;
    margin-top: 15px;
    color: #334155;
    font-size: 14px;
    line-height: 1.7;
    max-height: 220px;
    overflow-y: auto;
}

a {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #0f62b8;
    text-decoration: none;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="card">
    <div class="logo">
        <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals">
    </div>

    <h1>Delete User Safely</h1>

    <p>
        Enter a username. This will delete only this exact username and linked IDs.
    </p>

  

    <?php if ($_SERVER["REQUEST_METHOD"] === "POST" && $message !== ""): ?>
    <div class="<?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

    <?php if ($submitted && !empty($details)): ?>
        <div class="details">
            <?php foreach ($details as $line): ?>
                <div><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return confirm('Are you sure? This will delete only this exact username and linked IDs, not all users with the same email.');">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" required>

        <button type="submit" name="delete_user">Delete User Safely</button>
    </form>

    <a href="admin_dashboard.php">Back to Admin Dashboard</a>
</div>

</body>
</html>