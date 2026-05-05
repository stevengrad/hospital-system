<?php
include 'db_connect.php';

$accounts = [
    1 =>  ['username'=>'docahmed', 'password'=>'docT9@eQ3!'],
    2 =>  ['username'=>'doclaila', 'password'=>'docG5#Lm8$'],
    3 =>  ['username'=>'docmohamed', 'password'=>'docR2%hXa7'],
    4 =>  ['username'=>'docsalma', 'password'=>'docP7!sKd4'],
    5 =>  ['username'=>'dochany', 'password'=>'docM3@vQp9'],
    6 =>  ['username'=>'docfatma', 'password'=>'docK8#Trw2'],
    7 =>  ['username'=>'dockhaled', 'password'=>'docH4$gBn6'],
    8 =>  ['username'=>'docnoha', 'password'=>'docB1!qHz8'],
    9 =>  ['username'=>'doctarek', 'password'=>'docF6%eSu3'],
    10 => ['username'=>'docmona', 'password'=>'docD7@xLp5'],
    11 => ['username'=>'docsayed', 'password'=>'docW2#nCv8'],
    12 => ['username'=>'docamira', 'password'=>'docJ9!zRt3'],
    13 => ['username'=>'docali', 'password'=>'docL5@aYk7'],
    14 => ['username'=>'docdalia', 'password'=>'docU8%oPw4'],
    15 => ['username'=>'docmagdy', 'password'=>'docC6!dQj9'],
    16 => ['username'=>'dochoda', 'password'=>'docY3#tFs1'],
    17 => ['username'=>'docehab', 'password'=>'docZ7@wKr2'],
    18 => ['username'=>'docnadia', 'password'=>'docX9$eTm6'],
    19 => ['username'=>'docmazen', 'password'=>'docQ4!rNp5'],
    20 => ['username'=>'docsamy', 'password'=>'docV1@kGb8'],
    21 => ['username'=>'doczeinab', 'password'=>'docE6%uSw2'],
    22 => ['username'=>'docayman', 'password'=>'docA8$zQp7'],
    23 => ['username'=>'docmayar', 'password'=>'docS3!tMx4'],
    24 => ['username'=>'docghada', 'password'=>'docK9#bHd1'],
    25 => ['username'=>'docosama', 'password'=>'docN2@vJf6'],
];

foreach ($accounts as $id => $acc) {
    $username = $acc['username'];
    $passwordHash = password_hash($acc['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        UPDATE employees
        SET Username = ?, PasswordHash = ?
        WHERE EmployeeID = ?
    ");
    $stmt->bind_param("ssi", $username, $passwordHash, $id);
    $stmt->execute();
    echo "Updated doctor ID $id with username $username<br>";
}

echo "<br>Done. Delete this file now.";
