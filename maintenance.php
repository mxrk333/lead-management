<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow-x: hidden;
        }

        .maintenance-container {
            text-align: center;
            max-width: 600px;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: bounce 2s infinite;
            color: #ffd700;
        }

        .maintenance-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .maintenance-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .countdown-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .countdown-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .time-unit {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 1rem;
            min-width: 80px;
            backdrop-filter: blur(5px);
        }

        .time-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
            line-height: 1;
        }

        .time-label {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            margin: 2rem 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ffed4e);
            border-radius: 3px;
            width: 0%;
            animation: progress 3s ease-in-out infinite;
        }

        .contact-info {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .contact-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .contact-details {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .contact-item i {
            color: #ffd700;
        }

        .social-links {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            color: #ffd700;
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 10%;
            right: 20%;
            animation-delay: 1s;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }

        @keyframes progress {
            0% {
                width: 0%;
            }
            50% {
                width: 70%;
            }
            100% {
                width: 0%;
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 1rem;
            }

            .maintenance-title {
                font-size: 2rem;
            }

            .maintenance-subtitle {
                font-size: 1rem;
            }

            .maintenance-icon {
                font-size: 3rem;
            }

            .countdown-timer {
                gap: 0.5rem;
            }

            .time-unit {
                min-width: 60px;
                padding: 0.75rem;
            }

            .time-number {
                font-size: 1.5rem;
            }

            .contact-details {
                flex-direction: column;
                gap: 1rem;
            }

            .social-links {
                gap: 0.5rem;
            }

            .social-link {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .maintenance-title {
                font-size: 1.5rem;
            }

            .countdown-container {
                padding: 1rem;
            }

            .time-unit {
                min-width: 50px;
                padding: 0.5rem;
            }

            .time-number {
                font-size: 1.2rem;
            }

            .time-label {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1 class="maintenance-title">We'll Be Back Soon!</h1>
        
        <p class="maintenance-subtitle">
            We're currently performing scheduled maintenance to improve your experience. 
            Our team is working hard to get everything back online as quickly as possible.
        </p>

        <div class="countdown-container">
            <h3 class="countdown-title">Estimated Time Remaining</h3>
            <div class="countdown-timer" id="countdown">
                <div class="time-unit">
                    <span class="time-number" id="hours">00</span>
                    <span class="time-label">Hours</span>
                </div>
                <div class="time-unit">
                    <span class="time-number" id="minutes">00</span>
                    <span class="time-label">Minutes</span>
                </div>
                <div class="time-unit">
                    <span class="time-number" id="seconds">00</span>
                    <span class="time-label">Seconds</span>
                </div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>

        <div class="contact-info">
            <h3 class="contact-title">Need Immediate Assistance?</h3>
            <div class="contact-details">
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span>patigayon.innersparc@gmail.com</span>
                    <span> | </span>
                    <i class="fas fa-envelope"></i>
                    <span> gervacio.innersparc@gmail.com</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span>09194620030</span>
                </div>
            </div>
        </div>

        <div class="social-links">
            <a href="#" class="social-link" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-link" title="Twitter">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="social-link" title="Instagram">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="social-link" title="LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>
    </div>

    <script>
        // Set the maintenance end time (adjust as needed)
        // Example: 2 hours from now
        const maintenanceEndTime = new Date().getTime() + (2 * 60 * 60 * 1000);

        function updateCountdown() {
            const now = new Date().getTime();
            const timeLeft = maintenanceEndTime - now;

            if (timeLeft > 0) {
                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            } else {
                // Maintenance is over
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                
                // Optionally redirect or show a different message
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }

        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Add some interactive effects
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                const xPos = (x * speed * 50) - 25;
                const yPos = (y * speed * 50) - 25;
                
                shape.style.transform = `translate(${xPos}px, ${yPos}px)`;
            });
        });

        // Auto-refresh page every 5 minutes to check if maintenance is over
        setInterval(() => {
            // You can add a check here to see if the site is back online
            // For now, we'll just refresh the page
            console.log('Checking if maintenance is complete...');
        }, 5 * 60 * 1000);
    </script>
</body>
</html>
