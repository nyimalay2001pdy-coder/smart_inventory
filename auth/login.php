<?php
session_start();
require_once " config/db.php";

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all field";
    } else {
       $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username= ? ");
       $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

       if($user && $user['password'] === $password) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit();
       } else {
        $error = "Invalid username or password";
       }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome back</title>
     <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class=" bg-gray-300 flex justify-center items-center  ">
    <div class="  w-1/3  min-h-screen flex flex-col  items-center p-4 ">
        <img src="../images/user.jpg" alt="Profile" class="w-12 h-12 rounded-2xl ">
        <h1  class="font-semibold text-2xl text-white">Welcome Back</h1>
        <p class="text-gray-400">Sign in to account</p>
        <form
        action="" method="POST"
        class="p-8 w-3/2 flex flex-col gap-5 rounded-lg shadow-md bg-gray-800 mt-4"
      >
       <?php if (!empty($error)): ?>
        <div class="bg-red-500/10 border-red-700/100 rounded-xl w-full mt-4 p-3">
            <img src="../images/false.png" alt="Incorrect" class="w-4 h-4 rounded-full bg-red-500">
            
        </div>
        <?php endif; ?>
        <div class=" text-white">
          <label for="name">Username</label>
          <input
            type="text"
            id="name"
            name="username"
            placeholder="Enter your username"
            class="py-2 rounded-lg bg-gray-500 p-2 w-full"
          />
        </div>
        <div class=" text-white">
          <label for="name">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            class="py-2 rounded-lg bg-gray-500 p-2 w-full"
          />
        </div>
         <div>
        <button  type="submit" class="bg-blue-600 text-white text-center font-bold rounded-md py-2 inline-block w-full mt-4">Sign In
        </button>
      </div>
      </form>
      <p class="text-center mt-4">
        Don't have an account?
        <span><a href="register.php" class="text-red-500">Register here</a></span>
      </p>
    </div>
</body>
</html>