<?php

$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'recipe_sharing';
$conn = mysqli_connect($servername,$username, $password,$dbname) or die('Unable to connect');

if($conn-> connect_error){
    die('connection failed');
}else{
    //do nothing
    //echo 'connection successful';
}
?>