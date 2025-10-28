<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        .email-container {
            width: 600px;
            margin: 0 auto;
        }
        .header {
            background-color: #ffffff;
            text-align: center;
            padding: 20px;
        }
        .main-content {
            background-color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .footer {
            background-color: #ffffff;
            padding-top: 20px;
            text-align: center;
        }
        .social-icons img {
            margin: 0 10px;
        }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <table class="email-container">
                    <!-- Header Section -->
                    <!-- <tr>
                        <td class="header">
                            <img src="https://etobsnw.stripocdn.email/content/guids/CABINET_b33e602afa6bf032d061f66b4ecb7d8897b6931abf30aba194875b83a419cad5/images/affiniks1removebgpreview.png" alt="Logo" width="200">
                        </td>
                    </tr> -->

                    <!-- Main Content Section -->
                    <tr>
                        <td class="main-content">
                            <!-- <img src="https://etobsnw.stripocdn.email/content/guids/CABINET_67e080d830d87c17802bd9b4fe1c0912/images/55191618237638326.png" alt="" width="100"> -->
                            <h1>Forgot Password Request</h1>
                            <p>Hi , {{$name}}</p>
                            <p>We received a request to reset your password. Please use the following One-Time Passcode (OTP) to proceed with resetting your password.</p>
                            <h2 class="otp-code">Your OTP: {{ $otp }}</h2>
                            <p>Enter this code on the password reset page to continue. If you did not request a password reset, please ignore this email or contact our support team.</p>
                            <p><b>Note:</b> For security reasons, do not share this OTP with anyone. It is valid for a single use and will expire shortly.</p>
                            <!-- <p>If you have any questions or need assistance, reach out to our support team at <a href="mailto:info@filefort.in">info@filefort.in</a></p> -->
                            <!-- <p>Thank you for using Filefort!</p> -->
                            <p>Best regards,</p>
                            <!-- <p>Team Filefort</p> -->
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <!-- <tr>
                        <td class="footer">
                            <div class="social-icons">
                                <a href="https://www.facebook.com/filefort.in">
                                    <img src="https://etobsnw.stripocdn.email/content/assets/img/social-icons/logo-black/facebook-logo-black.png" alt="Fb" width="32">
                                </a>
                                <a href="https://www.instagram.com/filefort.in">
                                    <img src="https://etobsnw.stripocdn.email/content/assets/img/social-icons/logo-black/instagram-logo-black.png" alt="Inst" width="32">
                                </a>
                                <a href="https://www.linkedin.com/showcase/filefort/">
                                    <img src="https://etobsnw.stripocdn.email/content/assets/img/social-icons/logo-black/linkedin-logo-black.png" alt="In" width="32">
                                </a>
                            </div>
                            <p>Kh. No. 1746, First Floor, Dhoom Manikpur, G. T. Road, 2`   Nagar 203207 (UP)</p>
                            <p><b>©2025. All Rights Reserved Filefort.</b></p>
                        </td>
                    </tr> -->
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
