<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/brew+flex/css/index.css">
    <title>Fit Tech</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #caf0f8, #90e0ef, #0077b6);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background-size: 400% 400%;
    animation: gradient-animation 15s ease infinite;
}

@keyframes gradient-animation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.container {
    text-align: center;
    background: rgba(255, 255, 255, 0.95);
    padding: 50px 40px;
    border-radius: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    max-width: 700px;
    width: 90%;
    animation: fade-in 1s ease-in-out;
}

@keyframes fade-in {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
h1 {
    font-size: 3.5rem;
    font-weight: bold;
    letter-spacing: 3px;
    margin-bottom: 20px;
    background: linear-gradient(90deg, #0077b6, #00bfff);
    -webkit-background-clip: text; /* WebKit-specific */
    background-clip: text; /* Optional fallback */
    color: transparent;
}


.logo img {
    width: 100%;
    max-width: 400px;
    border-radius: 15px;
    margin: 30px 0;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}

.logo img:hover {
    transform: scale(1.05);
}

.est {
    font-size: 1.1rem;
    color: #023e8a;
    font-style: italic;
    margin-bottom: 25px;
}

.get-started-btn {
    display: inline-block;
    padding: 18px 36px;
    background: linear-gradient(90deg, #00bfff, #0077b6);
    color: #fff;
    border-radius: 40px;
    font-size: 1.3rem;
    text-decoration: none;
    font-weight: bold;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
    transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
}

.get-started-btn:hover {
    background: linear-gradient(90deg, #0077b6, #00bfff);
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

</style>

</head>

<body>
    <div class="container">
        <h1>FIT TECH</h1>
        <div class="logo">
            <img src="/brew+flex/assets/brewlogo2.png" alt="brew+flex">
        </div>
        <p class="est">est. 2024</p>
        <a href="/brew+flex/auth/login.php" class="get-started-btn">Get Started</a>
    </div>
</body>
</html>