<?php
session_start();
require_once "config/db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  if (empty($username) || empty($password)) {
    $error = "Please fill in all field";
  } elseif (strlen($password) < 6) {
    $error = "Password at least 6 character long.";
  } else {
    $check = $conn->prepare("SELECT id FROM users WHERE username= ?");
    $check->bind_param('s', $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
      $error = "Username already exit!.";
    } else {
      $stmt = $conn->prepare("INSERT INTO users(username,password)VALUES(?,?)");
      $stmt->bind_param('ss', $username, $password);
      
      if ($stmt->execute()) {
        $success = "Registration Successful!";
      } else {
        $error = "Registration Fail";
      }
      $stmt->close();
    }
    $check->close();
  }
}
?>


<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Document</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="flex items-center justify-center min-h-screen bg-slate-300">
  <div>
    <div class="text-center flex justify-center items-center flex-col mb-4">
      <img src="../images/user.jpg" alt="User" class="w-12 h-12 rounded-2xl" />
      <h1 class="font-semibold text-2xl">Create an account</h1>
      <p>Sign up to get started</p>
    </div>
    <form
      action="register.php" method="POST"
      class="p-8 w-96 flex flex-col gap-5 rounded-lg shadow-md bg-gray-800">
      <!--fail-->
      <?php if (!empty($error)): ?>
        <div
          class="border-red-600/70 p-2 rounded-lg flex items-center gap-8 bg-red-500/10 text-red-500">
          <p><img src="../images//false.png" alt="False" class="w-4 h-4 rounded-full bg-red-500"></p>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      <!--success-->
      <?php if (!empty($success)): ?>
        <div
          class="border-green-500/30 p-2 rounded-lg flex items-center justify-between bg-green-500/10 gap-8 text-green-500">
          <p><img src="../images/correct.png" alt="Correct" class="w-4 h-4 rounded-full bg-green-500"></p>
          <?php echo htmlspecialchars($success); ?>
          <p><a href="login.php" class="underline">Login-></a></p>
        </div>
      <?php endif; ?>
      <div class="flex flex-col text-white">
        <label for="name">Username</label>
        <input
          type="text"
          id="name"
          name="username"
          placeholder="Choose username"
          class="py-2 rounded-lg bg-gray-500 p-2" />
      </div>
      <div class="flex flex-col text-white">
        <label for="name">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Choose "
          class="py-2 rounded-lg bg-gray-500 p-2" />
      </div>
      <div>
        <button  type="submit" class="bg-blue-600 text-white text-center font-bold rounded-md py-2 inline-block w-full mt-4">Create Account
        </button>
      </div>
    </form>
    <p class="text-center mt-4">
      Already have an account?
      <span><a href="login.php" class="text-red-500">Login here</a></span>
    </p>
  </div>
</body>

</html>