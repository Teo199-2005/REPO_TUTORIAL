<?php
namespace App\Libraries;

class SupabaseEmailService
{
    private $supabaseUrl;
    private $supabaseKey;
    private $fromEmail;
    
    public function __construct()
    {
        $this->supabaseUrl = 'https://qycsakbshrdgwxbpusbg.supabase.co';
        $this->supabaseKey = env('SUPABASE_ANON_KEY', ''); // You'll set this in .env
        $this->fromEmail = 'noreply@lphs.edu.ph';
    }

    /**
     * Current school principal for the email signature block. Public schools
     * change their principal every few years, so the name is read from
     * system_settings (Admin > Settings) instead of being hardcoded here. Falls
     * back to the built-in default if the setting row is missing.
     */
    private function principalName(): string
    {
        helper('principal');

        return school_principal_name();
    }
    
    public function sendVerificationEmail($toEmail, $studentName, $lrn, $tempPassword = null)
    {
        $subject = 'CSCS Tap n Track - Your Enrollment Application Has Been Approved';
        
        $message = $this->getEmailTemplate($studentName, $lrn, $tempPassword);
        
        $result = $this->sendEmail($toEmail, $subject, $message);
        log_message('info', 'Email processing completed for: ' . $toEmail);
        return $result;
    }
    
    public function sendRejectionEmail($toEmail, $studentName)
    {
        $subject = 'CSCS Tap n Track - Enrollment Application Status Update';
        
        $message = $this->getRejectionEmailTemplate($studentName);
        
        $result = $this->sendEmail($toEmail, $subject, $message);
        log_message('info', 'Rejection email processing completed for: ' . $toEmail);
        return $result;
    }
    
    private function sendEmail($to, $subject, $htmlContent)
    {
        // Try Supabase Auth email first
        if ($this->sendViaSupabase($to, $subject, $htmlContent)) {
            return true;
        }
        
        // Fallback to direct SMTP
        return $this->sendViaSMTP($to, $subject, $htmlContent);
    }
    
    private function sendViaSupabase($to, $subject, $htmlContent)
    {
        try {
            $supabaseUrl = 'https://qycsakbshrdgwxbpusbg.supabase.co';
            $supabaseKey = $this->supabaseKey;
            
            if (empty($supabaseKey)) {
                log_message('info', 'Supabase key not configured, skipping Supabase email');
                return false;
            }
            
            // Use Supabase Auth to send email
            $data = [
                'email' => $to,
                'data' => [
                    'subject' => $subject,
                    'html_content' => $htmlContent
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $supabaseUrl . '/auth/v1/admin/users');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $supabaseKey,
                'apikey: ' . $supabaseKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                log_message('info', 'Email sent via Supabase to: ' . $to);
                return true;
            } else {
                log_message('info', 'Supabase email failed, trying SMTP fallback');
                return false;
            }
        } catch (Exception $e) {
            log_message('error', 'Supabase email error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function sendViaSMTP($to, $subject, $htmlContent)
    {
        $email = \Config\Services::email();
        $gmailPass = env('GMAIL_APP_PASSWORD', '');
        
        if (empty($gmailPass)) {
            log_message('info', 'GMAIL_APP_PASSWORD not configured - Email sending disabled');
            log_message('info', 'MANUAL EMAIL NEEDED - Send to: ' . $to . ' | Subject: ' . $subject);
            return false; // Return false to indicate email wasn't sent
        }
        
        $config = [
            'protocol' => 'smtp',
            'SMTPHost' => 'smtp.gmail.com',
            'SMTPUser' => 'lphscodenectars@gmail.com',
            'SMTPPass' => $gmailPass,
            'SMTPPort' => 587,
            'SMTPCrypto' => 'tls',
            'mailType' => 'html',
            'charset' => 'utf-8'
        ];
        
        $email->initialize($config);
        $email->setFrom('lphscodenectars@gmail.com', 'CSCS Tap n Track');
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($htmlContent);
        
        $result = $email->send();
        
        if (!$result) {
            log_message('error', 'SMTP Email failed: ' . $email->printDebugger());
            log_message('info', 'MANUAL EMAIL NEEDED - Send to: ' . $to . ' | Subject: ' . $subject);
            return false; // Return false when email fails
        } else {
            log_message('info', 'Email sent via SMTP to: ' . $to);
            return true;
        }
        
        return $result;
    }
    
    private function getEmailTemplate($studentName, $lrn, $tempPassword = null)
    {
        $principalName = $this->principalName();

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Times New Roman', Times, serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e40af; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .button { background: #1e40af; color: #ffffff !important; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
                .credentials { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 CSCS Tap n Track — Enrollment Approved!</h1>
                    <p>Cauayan South Central School</p>
                </div>
                
                <div class='content'>
                    <h2>Congratulations, {$studentName}!</h2>
                    
                    <p>Your enrollment application has been <strong>approved</strong> by our school administrators.</p>
                    
                    <div class='credentials'>
                        <h3>📧 Your Login Credentials:</h3>
                        <p><strong>LRN (Learner Reference Number):</strong> <code>{$lrn}</code></p>
                        <p><strong>Password:</strong> <code>{$tempPassword}</code></p>
                    </div>
                    
                    <p>You can now access the student portal using these credentials:</p>
                    
                    <a href='https://smslphs.site/login' class='button'>Login to Student Portal</a>
                    
                    <h3>⚠️ Important:</h3>
                    <ul>
                        <li>Use your LRN (not email) as your username to login</li>
                        <li>Please change your password after first login</li>
                        <li>Keep your login credentials secure</li>
                        <li>Contact the school office if you have any issues</li>
                    </ul>
                    
                    <p>Welcome to Cauayan South Central School! We look forward to your academic journey with us.</p>
                </div>
                
                <div class='footer'>
                    <p>Cauayan South Central School<br>
                    Mabini Street, District I, Cauayan City, Isabela, Philippines<br>
                    Principal: {$principalName}</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getRejectionEmailTemplate($studentName)
    {
        $principalName = $this->principalName();

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Times New Roman', Times, serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #8f2020; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .notice { background: #fdf1f1; border: 1px solid #f0d4d4; border-radius: 10px; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📋 CSCS Tap n Track — Enrollment Update</h1>
                    <p>Cauayan South Central School</p>
                </div>
                
                <div class='content'>
                    <h2>Dear {$studentName},</h2>
                    
                    <p>Thank you for your interest in enrolling at Cauayan South Central School.</p>
                    
                    <div class='notice'>
                        <h3>⚠️ Application Status Update</h3>
                        <p>After careful review, we regret to inform you that your enrollment application has not been approved at this time.</p>
                    </div>
                    
                    <h3>📞 Next Steps:</h3>
                    <ul>
                        <li>You may contact our admissions office for more information</li>
                        <li>Consider reapplying in the next enrollment period</li>
                        <li>Explore other educational opportunities that may be available</li>
                    </ul>
                    
                    <p>We appreciate your understanding and wish you the best in your educational journey.</p>
                </div>
                
                <div class='footer'>
                    <p>Cauayan South Central School<br>
                    Mabini Street, District I, Cauayan City, Isabela, Philippines<br>
                    Principal: {$principalName}</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public function sendPromotionEmail($toEmail, $studentName, $nextGradeLevel)
    {
        $subject = 'CSCS Tap n Track - Grade Promotion Approved for Next School Year';
        $message = $this->getPromotionEmailTemplate($studentName, $nextGradeLevel);
        $result = $this->sendEmail($toEmail, $subject, $message);
        log_message('info', 'Promotion email processing completed for: ' . $toEmail);
        return $result;
    }
    
    private function getPromotionEmailTemplate($studentName, $nextGradeLevel)
    {
        helper('grade_level');
        $principalName = $this->principalName();
        $nextGradeLabel = grade_level_label((int) $nextGradeLevel);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Times New Roman', Times, serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #15803d; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .button { background: #1e40af; color: #ffffff !important; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
                .promotion-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Congratulations on Your Promotion!</h1>
                    <p>Cauayan South Central School</p>
                </div>
                
                <div class='content'>
                    <h2>Dear {$studentName},</h2>
                    
                    <p>We are pleased to inform you that your application for next school year enrollment has been <strong>approved</strong>!</p>
                    
                    <div class='promotion-box'>
                        <h3>✅ Promotion Details:</h3>
                        <p><strong>Next Grade Level:</strong> {$nextGradeLabel}</p>
                        <p><strong>Status:</strong> Approved</p>
                    </div>
                    
                    <h3>📋 What's Next:</h3>
                    <ul>
                        <li>Your section assignment will be announced before the school year starts</li>
                        <li>Watch for updates on enrollment schedules and requirements</li>
                        <li>Prepare necessary documents for the new school year</li>
                        <li>Check your student portal regularly for announcements</li>
                    </ul>
                    
                    <a href='https://smslphs.site/login' class='button'>Login to Student Portal</a>
                    
                    <p>Congratulations on your academic progress! We look forward to seeing you in {$nextGradeLabel}.</p>
                </div>
                
                <div class='footer'>
                    <p>Cauayan South Central School<br>
                    Mabini Street, District I, Cauayan City, Isabela, Philippines<br>
                    Principal: {$principalName}</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
