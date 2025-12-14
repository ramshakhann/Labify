<?php
include "db.php";

if(isset($_POST['signup'])){

    $name  = $_POST['name'];
    $email = $_POST['email'];
    $role  = $_POST['role'];

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $query = "INSERT INTO users(full_name, email, password, role)
              VALUES('$name', '$email', '$password', '$role')";

    if(mysqli_query($conn, $query)){
        header("Location: login.php");
    }
    else{
        echo "Error!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sign Up - Labify</title>
<link rel="stylesheet" href="assets/signup.css">

</head>
<body>

<div class="box">
<h2 style="text-align:center;color:#64ffda">Create Account</h2>

<form method="POST">
    <input type="text" name="name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="role">
        <option value="tester">Tester</option>
        <option value="lab_manager">Lab Manager</option>
    </select>

    <button name="signup">Sign Up</button>
</form>
</div>

</body>
</html>
