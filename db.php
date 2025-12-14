<?php
$conn = mysqli_connect("localhost", "root", "", "labify");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}
?>
