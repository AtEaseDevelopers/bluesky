<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page Not Found</title>
    <link rel="stylesheet" href="{{ asset('css/404-styles.css') }}"> <!-- Include your CSS file -->
</head>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .container {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    h1 {
        font-size: 3em;
        color: #ff5252; /* Red color */
    }

    p {
        font-size: 1.2em;
        margin-bottom: 20px;
    }

    .btn {
        display: inline-block;
        padding: 10px 20px;
        font-size: 1.2em;
        color: #fff;
        background-color: #448aff; /* Blue color */
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    .btn:hover {
        background-color: #2979ff; /* Darker blue on hover */
    }

</style>
<body>
<div class="container">
        <div class="content">
            <h1>404 - Page Not Found</h1>
            <p>The page you are looking for doesn't exist or has been moved.</p>
            <p>You will be redirected to the home page in <span id="countdown">5</span> seconds.</p>
            <a href="/" class="btn">Go back to the home page</a>
        </div>
    </div>

    <script>
        // const countdownElement = document.getElementById('countdown');
        // let countdown = 5; // Set the countdown duration in seconds

        // const countdownInterval = setInterval(() => {
        //     countdown--;
        //     countdownElement.textContent = countdown;

        //     if (countdown <= 0) {
        //         clearInterval(countdownInterval);
        //         window.location.href = '/'; // Redirect to the home page
        //     }
        // }, 1000);
    </script>
</body>
</html>
